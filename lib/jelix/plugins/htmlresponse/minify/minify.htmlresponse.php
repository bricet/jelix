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

            $this->response->setCSSLinks($this->generateMinifyList($this->response->getCSSLinks(), 'excludeCSS'));
            $this->response->setCSSIELinks($this->generateMinifyList($this->response->getCSSIELinks(), 'excludeCSS'));
        }

        if ($gJConfig->jResponseHtml['minifyJS']) {
            if($gJConfig->jResponseHtml['minifyExcludeJS'] ) {
                $this->excludeJS = explode( ',', $gJConfig->jResponseHtml['minifyExcludeJS'] );
            }
            $this->response->setJSLinks($this->generateMinifyList($this->response->getJSLinks(), 'excludeJS'));
            $this->response->setJSIELinks($this->generateMinifyList($this->response->getJSIELinks(), 'excludeJS'));
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
     * @param string $exclude  name of the property containing the list of excluded files
     * @return array list of urls to insert in the html page
     */
    protected function generateMinifyList($list, $exclude) {
        global $gJConfig;
        $pendingList = array();
        $pendingParameters = false;
        $resultList = array();

        foreach ($list as $url=>$parameters) {
            ksort($parameters);
            if ($pendingParameters === false) {
                $pendingParameters = $parameters;
                $pendingList[] = $url;
                continue;
            }
            $pathNotAbsolute = (strpos($url,'http://') === false);
            if ($pendingParameters == $parameters
                && !in_array($url, $this->$exclude)
                && $pathNotAbsolute) {
                $pendingList[] = $url;
            }
            else {
                $resultList[$this->generateMinifyUrl($pendingList)] = $pendingParameters;
                if (!$pathNotAbsolute) { // for absolute url, we put directly in the result
                                        // we won't try to minify it or combine it with an other file
                    $resultList[$url] = $parameters;
                    $pendingList = array();
                    $pendingParameters = false;
                }
                else {
                    $pendingList = array($url);
                    $pendingParameters = $parameters;
                }
            }
        }
        if ($pendingParameters !== false && count($pendingList)) {
            $resultList[$this->generateMinifyUrl($pendingList)] = $pendingParameters;
        }
        return $resultList;
    }

    protected function generateMinifyUrl($urlsList) {
        global $gJConfig;
        $url = $gJConfig->urlengine['basePath'].$gJConfig->jResponseHtml['minifyEntryPoint'].'?f=';
        $url .= implode(',', $urlsList);
        return $url;
    }
}
