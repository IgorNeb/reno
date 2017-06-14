<?php
/**
 * Class Minify_HTML
 * @package Minify
 */

/**
 * Compress HTML
 *
 * This is a heavy regex-based removal of whitespace, unnecessary comments and
 * tokens. IE conditional comments are preserved. There are also options to have
 * STYLE and SCRIPT blocks compressed by callback functions.
 *
 * A test suite is available.
 *
 * @package Minify
 * @author Stephen Clay <steve@mrclay.org>
 */
class MinifyHTML {
    /**
     * @var boolean
     */
    protected $_jsCleanComments = true;
    protected $_development = true;

    /**
     * "Minify" an HTML page
     *
     * @param string $html
     *
     * @param array $options
     *
     * 'cssMinifier' : (optional) callback function to process content of STYLE
     * elements.
     *
     * 'jsMinifier' : (optional) callback function to process content of SCRIPT
     * elements. Note: the type attribute is ignored.
     *
     * 'xhtml' : (optional boolean) should content be treated as XHTML1.0? If
     * unset, minify will sniff for an XHTML doctype.
     *
     * @return string
     */
    public static function minify($html, $options = array()) {
        $min = new self($html, $options);
        return $min->process();
    }


    /**
     * Create a minifier object
     *
     * @param string $html
     *
     * @param array $options
     *
     * 'cssMinifier' : (optional) callback function to process content of STYLE
     * elements.
     *
     * 'jsMinifier' : (optional) callback function to process content of SCRIPT
     * elements. Note: the type attribute is ignored.
     *
     * 'jsCleanComments' : (optional) whether to remove HTML comments beginning and end of script block
     *
     * 'xhtml' : (optional boolean) should content be treated as XHTML1.0? If
     * unset, minify will sniff for an XHTML doctype.
     *
     * @return null
     */
    public function __construct($html, $options = array())
    {
        $this->_html = str_replace("\r\n", "\n", trim($html));
        if (isset($options['xhtml'])) {
            $this->_isXhtml = (bool)$options['xhtml'];
        }
        if (isset($options['cssMinifier'])) {
            $this->_cssMinifier = $options['cssMinifier'];
        }
        if (isset($options['jsMinifier'])) {
            $this->_jsMinifier = $options['jsMinifier'];
        }
        if (isset($options['jsCleanComments'])) {
            $this->_jsCleanComments = (bool)$options['jsCleanComments'];
        }
        if (isset($options['development'])) {
            $this->_development = (bool)$options['development'];
        }        
    }


    /**
     * Minify the markeup given in the constructor
     * 
     * @return string
     */
    public function process()
    {
        if ($this->_isXhtml === null) {
            $this->_isXhtml = (false !== strpos($this->_html, '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML'));
        }
        
        $this->_replacementHash = 'MINIFYHTML' . md5($_SERVER['REQUEST_TIME']);
        $this->_placeholders = array();
        
        // remove HTML comments (not containing IE conditional comments).
        $this->_html = preg_replace_callback(
            '/<!--([\\s\\S]*?)-->/'
            ,array($this, '_commentCB')
            ,$this->_html);
        
        if ($this->_development){
           
            //if (IS_DEVELOPER) {
            //get .js to minify
            //$js_pattern = '/<script language="JavaScript" type="text\/javascript" src="(.*?)"><\/script>/';
            //<script src="/js/jquery-ui.min.js"></script>
            $js_pattern = '/<script type="text\/javascript"(.*)src="(.*?)"><\/script>/';
            preg_match_all($js_pattern, $this->_html, $output_js_array);
            //x($output_js_array);die();
            $file_compiled = 0;
            
            if (isset($output_js_array[2])) {                
                foreach ($output_js_array[2] as $i => $file) {
                    $filepath = SITE_ROOT . trim($file, '/');
                    if (!file_exists($filepath)) {
                        unset($output_js_array[2][$i]);
                    } else {
                        //время изменения файлов
                        $compiled_stat = stat($filepath);                            
                        if ($compiled_stat['mtime'] > $file_compiled) {
                            $file_compiled = $compiled_stat['mtime'];
                        }
                    }
                }
                $output_js_array[1] = $output_js_array[2];                
            }
            
            $output_js_array[1] = globalVar( $output_js_array[1],array() );
            if ( count($output_js_array[1]) ){

                $output_js_array[1] = array_unique($output_js_array[1]);
                $js = "";

                $js_path = md5( implode("|", $output_js_array[1]) );
                $js_path = CACHE_ROOT . DIRECTORY_SEPARATOR . "js" . DIRECTORY_SEPARATOR . $js_path . ".js";

                if (is_file($js_path) ){  
                    //удаляем, если были изменения в файлах
                    $compiled_stat = stat($js_path); 
                    if ($file_compiled > $compiled_stat['mtime']) {
                        unlink($js_path);
                    } 
                }
                
                if (!is_file($js_path) ){                
                    reset($output_js_array[1]);
                    while ( list(,$file)=each($output_js_array[1]) ){
                        $path = $_SERVER['DOCUMENT_ROOT'] . $file;
                        $realpath = realpath($path);
                        if (false === $realpath || ! is_file($realpath)) continue;

                        $js .= "\r\n" . $this->_removeScriptCB(file_get_contents($realpath),true);       
                    }                

                    if (!is_dir(dirname($js_path))) {
                        makedir(dirname($js_path), 0777, true);
                    }          

                    file_put_contents( $js_path, $js);
                    $js = gzencode($js, 9, FORCE_GZIP);
                    file_put_contents( $js_path . ".gz", $js);
                }

                $js_path = delta_path($js_path);
                $js_path = Uploads::getURL( $js_path );
                $js_path = str_replace('//', '/', $js_path);
                $cc = count($output_js_array[0]) - 1;
                if (isset($output_js_array[0][$cc])) {
                     
                    $this->_html = str_replace( $output_js_array[0][$cc], "<script language=\"javascript\" type=\"text/javascript\" src='". $js_path ."'></script>", $this->_html);
                    
                } else {
                    ////gzip.php?file=
                    $this->_html = str_replace( $output_js_array[0][0], "<script language=\"javascript\" type=\"text/javascript\" src='". $js_path ."'></script>", $this->_html);
                }
                $this->_html = preg_replace($js_pattern,"",$this->_html);
            }
           // } 
           
            //if (IS_DEVELOPER) {
            //get .css to minify     <link href="{#DESIGN_URL}css/bootstrap.min.css" rel="stylesheet"/>
            //$css_pattern = '/<link rel="stylesheet" href="(.*?)" type="text\/css">/';
            $css_pattern = '/<link href="(.*?)" rel="stylesheet"(.*?)>/';
            preg_match_all($css_pattern, $this->_html, $output_css_array);
           
            $file_compiled = 0;
            if (isset($output_css_array[1])) {
                foreach ($output_css_array[1] as $i => $file) {
                    $filepath = SITE_ROOT . trim($file, '/');
                    if (!file_exists($filepath)) {
                        unset($output_css_array[1][$i]);
                    } else {
                        //время изменения файлов
                        $compiled_stat = stat($filepath);                            
                        if ($compiled_stat['mtime'] > $file_compiled) {
                            $file_compiled = $compiled_stat['mtime'];
                        }
                    }
                }
            }
           
            $output_css_array[1] = globalVar( $output_css_array[1],array() );
            if ( count($output_css_array[1]) && $this->_cssMinifier ){

                $output_css_array[1] = array_unique($output_css_array[1]);
                $css = "";

                $css_path = md5( implode("|", $output_css_array[1]) );
                $css_path = CACHE_ROOT . DIRECTORY_SEPARATOR . "css" . DIRECTORY_SEPARATOR . $css_path . ".css";

                if (is_file($css_path) ){  
                    //удаляем, если были изменения в файлах
                    $compiled_stat = stat($css_path); 
                    if ($file_compiled > $compiled_stat['mtime']) {
                        unlink($css_path);
                    } 
                }
                
                if (!is_file($css_path) ){                
                    reset($output_css_array[1]);
                    while ( list(,$file)=each($output_css_array[1]) ){
                        $path = $_SERVER['DOCUMENT_ROOT'] . $file;
                        $realpath = realpath($path);
                        if (false === $realpath || ! is_file($realpath)) continue;

                        $css .= "\r\n" . $this->_removeStyleCB(file_get_contents( $realpath ), true, $realpath );      
                    }              

                    if (!is_dir(dirname($css_path))) {
                            makedir(dirname($css_path), 0777, true);
                    }                

                    file_put_contents( $css_path, $css);
                    $css = gzencode($css, 9, FORCE_GZIP);
                    file_put_contents( $css_path . ".gz", $css);
                }

                $css_path = delta_path($css_path);
                $css_path = Uploads::getURL( $css_path );

                $this->_html = str_replace( $output_css_array[0][0], "<link rel=\"stylesheet\" href='/gzip.php?file=". str_replace('//', '/', $css_path) ."' type=\"text/css\">", $this->_html);
                $this->_html = preg_replace($css_pattern,"",$this->_html);
            } 
            //}
        } 
        
        // replace SCRIPTs (and minify) with placeholders
        $this->_html = preg_replace_callback(
            '/(\\s*)<script(\\b[^>]*?>)([\\s\\S]*?)<\\/script>(\\s*)/i'
            ,array($this, '_removeScriptCB')
            ,$this->_html);
        
        // replace STYLEs (and minify) with placeholders
        $this->_html = preg_replace_callback(
            '/\\s*<style(\\b[^>]*>)([\\s\\S]*?)<\\/style>\\s*/i'
            ,array($this, '_removeStyleCB')
            ,$this->_html);
        
        // replace PREs with placeholders
        $this->_html = preg_replace_callback('/\\s*<pre(\\b[^>]*?>[\\s\\S]*?<\\/pre>)\\s*/i'
            ,array($this, '_removePreCB')
            ,$this->_html);
        
        // replace TEXTAREAs with placeholders
        $this->_html = preg_replace_callback(
            '/\\s*<textarea(\\b[^>]*?>[\\s\\S]*?<\\/textarea>)\\s*/i'
            ,array($this, '_removeTextareaCB')
            ,$this->_html);
        
        // trim each line.
        // @todo take into account attribute values that span multiple lines.
        $this->_html = preg_replace('/^\\s+|\\s+$/m', '', $this->_html);
        
        // remove ws around block/undisplayed elements
        $this->_html = preg_replace('/\\s+(<\\/?(?:area|base(?:font)?|blockquote|body'
            .'|caption|center|cite|col(?:group)?|dd|dir|div|dl|dt|fieldset|form'
            .'|frame(?:set)?|h[1-6]|head|hr|html|legend|li|link|map|menu|meta'
            .'|ol|opt(?:group|ion)|p|param|t(?:able|body|head|d|h||r|foot|itle)'
            .'|ul)\\b[^>]*>)/i', '$1', $this->_html);
        
        // remove ws outside of all elements
        $this->_html = preg_replace(
            '/>(\\s(?:\\s*))?([^<]+)(\\s(?:\s*))?</'
            ,'>$1$2$3<'
            ,$this->_html);
        
        // use newlines before 1st attribute in open tags (to limit line lengths)
        $this->_html = preg_replace('/(<[a-z\\-]+)\\s+([^>]+>)/i', "$1\n$2", $this->_html);
        
        // fill placeholders
        $this->_html = str_replace(
            array_keys($this->_placeholders)
            ,array_values($this->_placeholders)
            ,$this->_html
        );
        // issue 229: multi-pass to catch scripts that didn't get replaced in textareas
        $this->_html = str_replace(
            array_keys($this->_placeholders)
            ,array_values($this->_placeholders)
            ,$this->_html
        );
        return $this->_html;
    }
    
    protected function _commentCB($m)
    {
        return (0 === strpos($m[1], '[') || false !== strpos($m[1], '<!['))
            ? $m[0]
            : '';
    }
    
    protected function _reservePlace($content)
    {
        $placeholder = '%' . $this->_replacementHash . count($this->_placeholders) . '%';
        $this->_placeholders[$placeholder] = $content;
        return $placeholder;
    }

    protected $_isXhtml = null;
    protected $_replacementHash = null;
    protected $_placeholders = array();
    protected $_cssMinifier = null;
    protected $_jsMinifier = null;

    protected function _removePreCB($m)
    {
        return $this->_reservePlace("<pre{$m[1]}");
    }
    
    protected function _removeTextareaCB($m)
    {
        return $this->_reservePlace("<textarea{$m[1]}");
    }

    protected function _removeStyleCB($m,$inline=false,$path="")
    {
        if (!$inline){
            $openStyle = "<style{$m[1]}";
            $css = $m[2];
        }
        else $css = $m;
        // remove HTML comments
        $css = preg_replace('/(?:^\\s*<!--|-->\\s*$)/', '', $css);
        
        // remove CDATA section markers
        $css = $this->_removeCdata($css);
        
        // minify
        $minifier = $this->_cssMinifier
            ? $this->_cssMinifier
            : 'trim';
        
        if ( $path ){
            $css_path = delta_path($path);
            $css_path = Uploads::getURL( $css_path );
            $css_path = substr( $css_path, 0, strrpos($css_path, "/") + 1 );
            
            $css = call_user_func_array($minifier, array( $css, array('prependRelativePath'=>$css_path) ));
        }
        else $css = call_user_func($minifier, $css);
        if ($inline) return $css;
        
        return $this->_reservePlace($this->_needsCdata($css)
            ? "{$openStyle}/*<![CDATA[*/{$css}/*]]>*/</style>"
            : "{$openStyle}{$css}</style>"
        );
    }

    protected function _removeScriptCB( $m,$inline=false )
    {  
        if (!$inline){
            $openScript = "<script{$m[2]}";
            $js = $m[3];
        }
        else $js = $m;
        
        if (!$inline){
            // whitespace surrounding? preserve at least one space
            $ws1 = ($m[1] === '') ? '' : ' ';
            $ws2 = ($m[4] === '') ? '' : ' ';
        }
        
        // remove HTML comments (and ending "//" if present)
        if ($this->_jsCleanComments) {
            $js = preg_replace('/(?:^\\s*<!--\\s*|\\s*(?:\\/\\/)?\\s*-->\\s*$)/', '', $js);
        }

        // remove CDATA section markers
        $js = $this->_removeCdata($js);
        
        // minify
        $minifier = $this->_jsMinifier
            ? $this->_jsMinifier
            : 'trim';
        $js = call_user_func($minifier, $js);
        if ($inline) return $js;
        
        return $this->_reservePlace($this->_needsCdata($js)
            ? "{$ws1}{$openScript}/*<![CDATA[*/{$js}/*]]>*/</script>{$ws2}"
            : "{$ws1}{$openScript}{$js}</script>{$ws2}"
        );
    }

    protected function _removeCdata($str)
    {
        return (false !== strpos($str, '<![CDATA['))
            ? str_replace(array('<![CDATA[', ']]>'), '', $str)
            : $str;
    }
    
    protected function _needsCdata($str)
    {
        return ($this->_isXhtml && preg_match('/(?:[<&]|\\-\\-|\\]\\]>)/', $str));
    }
}
