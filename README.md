What is jelix-stylus-plugin ?
==============================

This project is a plugin for [Jelix](http://jelix.org) PHP framework. It allows you to use easily [stylus](http://learnboost.github.com/stylus/) dynamic stylesheet language in Jelix.

This is an htmlresponse plugin.



Installation
============

Under Jelix default configuration, create an "htmlresponse" directory in your project's "plugins" directory.

Clone this repository in that directory.



This plugin needs node.js with stylus module to be installed.






Usage
=====

When including a CSS file (e.g. with addCSSLink()) you should set 'stylus'=>true as a param.

E.g. in your response :

`$this->addCSSLink($gJConfig->urlengine['basePath'].'themes/'.$gJConfig->theme.'/Css/style.styl', array( 'stylus' => true ));`

Your config file must activate stylus plugin :

    [jResponseHtml]
    plugins=stylus

N.B. : the directories containing stylus files should be writable by your web server ! Indeed, compiled files will be written in that very same directory so that relative urls go on working ...



Config
======

You can configure stylus's behviour regarding compilation:

    [jResponseHtml]
    ;...
    ; always|onchange|once
    stylus_compile=always

If stylus\_compile's value is not valid or empty, its default value is onchange.

* always : compile stylus file on all requests
* onchange : compile stylus file only if it has changed
* once : compile stylus file once and never compile it again (until compiled file is removed)



You may also set stylus modle path for node with :
`stylus_node_paths=/usr/local/lib/node_modules`

And finally, you can set node's binary path with (default value is /usr/bin/node) :
`stylus_nodejs_bin_path=/usr/local/bin/node`

