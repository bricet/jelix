<?php
/**
* @package     jelix
* @subpackage  responsehtml_plugin
* @author      Laurent Jouanneau
* @copyright   2010 Laurent Jouanneau
* @link        http://jelix.org
* @licence     GNU Lesser General Public Licence see LICENCE file or http://www.gnu.org/licenses/lgpl.html
*/

/**
* plugin for jResponseHTML, which minify css and js files
*/
class minifyHTMLResponsePlugin implements jIHTMLResponsePlugin {

    protected $response = null;

    protected $excludeCSS = array();

    protected $excludeJS = array();

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
        if ($gJConfig->jResponseHtml['minifyCSS']) {
            if ($gJConfig->jResponseHtml['minifyExcludeCSS']) {
                $this->excludeCSS = explode( ',', $gJConfig->jResponseHtml['minifyExcludeCSS'] );
            }

            $this->response->setCSSLinks($this->generateMinifyList($this->response->getCSSLinks(), jMinifier::TYPE_CSS));
            $this->response->setCSSIELinks($this->generateMinifyList($this->response->getCSSIELinks(), jMinifier::TYPE_CSS));
        }

        if ($gJConfig->jResponseHtml['minifyJS']) {
            if($gJConfig->jResponseHtml['minifyExcludeJS'] ) {
                $this->excludeJS = explode( ',', $gJConfig->jResponseHtml['minifyExcludeJS'] );
            }
            $this->response->setJSLinks($this->generateMinifyList($this->response->getJSLinks(), jMinifier::TYPE_JS));
            $this->response->setJSIELinks($this->generateMinifyList($this->response->getJSIELinks(), jMinifier::TYPE_JS));
        }
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

    /**
     * generate a list of urls for minify. It combines urls if possible
     * @param array $list  key=url, values = attributes/parameters
     * @param string $type  jMinifier::TYPE_JS or jMinifier::TYPE_CSS
     * @return array list of urls to insert in the html page
     */
    protected function generateMinifyList($list, $type) {
        global $gJConfig;
        $pendingList = array();
        $pendingParameters = false;
        $resultList = array();

        $excludeList = array();
        switch( $type ) {
        case TYPE_JS:
            $excludeList = $this->excludeJS;
            break;
        case TYPE_CSS:
            $excludeList = $this->excludeCSS;
            break;
        }

        foreach ($list as $url=>$parameters) {
            $pathAbsolute = (strpos($url,'http://') !== false);
            if( $pathAbsolute || in_array($url, $excludeList) ) {
                // for absolute or exculded url, we put directly in the result
                // we won't try to minify it or combine it with an other file
                $resultList[$url] = $parameters;
                continue;
            }
            ksort($parameters);
            if ($pendingParameters === false) {
                $pendingParameters = $parameters;
                $pendingList[] = $url;
                continue;
            }
            if ($pendingParameters == $parameters) {
                $pendingList[] = $url;
            }
            else {
                foreach( $this->generateMinifyUrls($pendingList, $type) as $minifiedUrl ) {
                    $resultList[$minifiedUrl] = $pendingParameters;
                }
                $pendingList = array($url);
                $pendingParameters = $parameters;
            }
        }
        if ($pendingParameters !== false && count($pendingList)) {
            foreach( $this->generateMinifyUrls($pendingList, $type) as $minifiedUrl ) {
                $resultList[$minifiedUrl] = $pendingParameters;
            }
        }
        return $resultList;
    }

    protected function generateMinifyUrls($urlsList, $type) {

        global $gJConfig;

        $addUniqueId = false;
        switch( $type ) {
        case TYPE_JS:
            $addUniqueId = $gJConfig->jResponseHtml['jsUniqueUrlId'];
            break;
        case TYPE_CSS:
            $addUniqueId = $gJConfig->jResponseHtml['cssUniqueUrlId'];
            break;
        }

        $urls = array();

        if( $gJConfig->jResponseHtml['minifyUsingEntryPoint'] ) {
            $url = $gJConfig->urlengine['basePath'].$gJConfig->jResponseHtml['minifyEntryPoint'].'?f=';
            $url .= implode(',', $urlsList);
            $urls[] = $url;
        } else {
            foreach (jMinifier::minify( $urlsList, $type ) as $minifiedPath=>$minifiedUrl ) {
                //foreach() beacause jMinifier::minify() may return several pathes
                $urlUniqueId = '';
                if( $addUniqueId && file_exists(JELIX_APP_WWW_PATH . $minifiedPath) ) {
                    $urlUniqueId = filemtime(JELIX_APP_WWW_PATH . $minifiedPath);
                }
                $url = $minifiedUrl;
                if( $urlUniqueId != '' ) {
                    if( strpos($url, '?') === false ) {
                        $url .= '?';
                    } else {
                        $url .= '&';
                    }
                    $url .= $urlUniqueId;
                }
                $urls[] = $url;
            }
        }

        return $urls;
    }
}
