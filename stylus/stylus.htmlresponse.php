<?php
/**
* @package     
* @subpackage  
* @author      Brice Tencé
* @copyright   2011 Brice Tencé
* @link        
* @licence     GNU Lesser General Public Licence see LICENCE file or http://www.gnu.org/licenses/lgpl.html
*/

/**
* plugin for jResponseHTML, which processes stylus files using assetic
*/

define('STYLUS_COMPILE_ALWAYS', 1 );
define('STYLUS_COMPILE_ONCHANGE', 2 ); //default value
define('STYLUS_COMPILE_ONCE', 3 );

class stylusHTMLResponsePlugin implements jIHTMLResponsePlugin {

    protected $response = null;

    public function __construct(jResponse $c) {
        $this->response = $c;
    }

    /**
     * called just before the jResponseBasicHtml::doAfterActions() call
     */
    public function afterAction() {
    }

    /**
     * called when the content is generated, and potentially sent, except
     * the body end tag and the html end tags. This method can output
     * directly some contents.
     */
    public function beforeOutput() {
        if (!($this->response instanceof jResponseHtml))
            return;
        global $gJConfig;

        $compileFlag = STYLUS_COMPILE_ONCHANGE;
        if( isset($gJConfig->jResponseHtml['stylus_compile']) ) {
            switch($gJConfig->jResponseHtml['stylus_compile']) {
            case 'always':
                $compileFlag = STYLUS_COMPILE_ALWAYS;
                break;
            case 'onchange':
                $compileFlag = STYLUS_COMPILE_ONCHANGE;
                break;
            case 'once':
                $compileFlag = STYLUS_COMPILE_ONCE;
                break;
            }
        }

        $inputCSSLinks = $this->response->getCSSLinks();
        $outputCSSLinks = array();

        foreach( $inputCSSLinks as $inputCSSLinkUrl=>$CSSLinkParams ) {
            $CSSLinkUrl = $inputCSSLinkUrl;
            if( isset($CSSLinkParams['stylus']) ) {
                if( $CSSLinkParams['stylus'] ) {
                    //we suppose url starts with basepath. Other cases should not have a "'stylus' => true" param ...
                    if( substr($CSSLinkUrl, 0, strlen($gJConfig->urlengine['basePath'])) != $gJConfig->urlengine['basePath'] ) {
                        throw new Exception("File $CSSLinkUrl seems not to be located in your basePath : it can not be processed with Assetic's StylusFilter");
                    } else {
                        $filePath = jApp::wwwPath() . substr($CSSLinkUrl, strlen($gJConfig->urlengine['basePath']));

                        if( !is_file($filePath) ) {
                        } else {
                            $outputSuffix = '';
                            if( substr($filePath, -5) != '.styl' ) {
                                //append .styl at the end of filename if it is not already the case ...
                                $outputSuffix .= '.styl';
                            }
                            $outputSuffix .= '.css';
                            $outputPath = $filePath.$outputSuffix;

                            $compile = true;
                            if( is_file($outputPath) ) {
                                if( ($compileFlag == STYLUS_COMPILE_ALWAYS) ) {
                                    unlink($outputPath);
                                } elseif( ($compileFlag == STYLUS_COMPILE_ONCE) ) {
                                    $compile = false;
                                } elseif( ($compileFlag == STYLUS_COMPILE_ONCHANGE) && filemtime($filePath) <= filemtime($outputPath) ) {
                                    $compile = false;
                                }
                            }
                            if( $compile ) {
                                file_put_contents( $outputPath, $this->compileStylus($filePath) );
                            }
                            $CSSLinkUrl = $CSSLinkUrl . $outputSuffix;
                        }
                    }
                }
                unset($CSSLinkParams['stylus']);
            }

            $outputCSSLinks[$CSSLinkUrl] = $CSSLinkParams;
        }

        $this->response->setCSSLinks( $outputCSSLinks );
    }

    /**
     * called just before the output of an error page
     */
    public function atBottom() {
    }

    /**
     * called just before the output of an error page
     */
    public function beforeOutputError() {
    }





    private function compileStylus( $filePath ) {

        global $gJConfig;

        static $format = <<<'EOF'
var stylus = require('stylus');
var sys    = require('sys');

stylus(%s, %s).render(function(e, css){
    if (e) {
        throw e;
    }

    sys.print(css);
    process.exit(0);
});

EOF;

        // parser options
        $stylusOptions = array();
        $stylusOptions['paths'] = array(dirname($filePath));
        $stylusOptions['filename'] = basename($filePath);

        // node.js configuration
        $env = array();
        if(isset($gJConfig->jResponseHtml['stylus_node_paths']) && $gJConfig->jResponseHtml['stylus_node_paths'] != '') {
            $env['NODE_PATH'] = $gJConfig->jResponseHtml['stylus_node_paths'];
        }

        $nodeBinPath = '/usr/bin/node';
        if(isset($gJConfig->jResponseHtml['stylus_nodejs_bin_path']) && $gJConfig->jResponseHtml['stylus_nodejs_bin_path'] != '') {
            $nodeBinPath = $gJConfig->jResponseHtml['stylus_nodejs_bin_path'];
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'jelix_stylus');

        file_put_contents($tempFile, sprintf($format,
            json_encode(file_get_contents($filePath)),
            json_encode($stylusOptions)
        ));

        $cmd = escapeshellarg($nodeBinPath) . ' ' . escapeshellarg($tempFile);
        if( defined('PHP_WINDOWS_VERSION_MAJOR') ) {
            $cmd = 'cmd /V:ON /E:ON /C "'.$nodeBinPath.'" ' . escapeshellarg($tempFile);
        }

        $descriptors = array(array('pipe', 'r'), array('pipe', 'w'), array('pipe', 'w'));
        $process = proc_open($cmd, $descriptors, $pipes, jApp::wwwPath(), $env, array('suppress_errors' => true, 'binary_pipes' => true, 'bypass_shell' => false));

        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }
        $allPipes = $pipes;
        fclose($pipes[0]);
        unset($pipes[0]);

        $out = '';
        $err = '';

        $w = array();
        while( $pipes ) {
            $e = null;

            $n = @stream_select($pipes, $w, $e, $this->timeout);

            if (false === $n) {
                break;
            } elseif ($n === 0) {
                proc_terminate($process);
                break;
            }

            foreach ($pipes as $pipe) {
                $type = array_search($pipe, $allPipes);
                $data = fread($pipe, 8192);
                if (strlen($data) > 0) {
                    switch( $type ) {
                    case 1:
                        $out .= $data;
                        break;
                    case 2:
                        $err .= $data;
                        break;
                    }
                }
                if (false === $data || feof($pipe)) {
                    fclose($pipe);
                    unset($pipes[$type]);
                }
            }
        }

        $status = proc_get_status($process);

        $time = 0;
        while (1 == $status['running'] && $time < 1000000) {
            $time += 1000;
            usleep(1000);
            $status = proc_get_status($process);
        }

        $exitcode = proc_close($process);

        unlink($tempFile);

        if( $err != '' ) {
            trigger_error( "Stylus error for '$filePath' : $err", E_USER_ERROR );
        }

        return $out;
    }
}
