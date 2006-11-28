<?php
/**
* @package     jelix
* @subpackage  core
* @version     $Id$
* @author      Jouanneau Laurent
* @contributor
* @copyright   2005-2006 Jouanneau laurent
* @link        http://www.jelix.org
* @licence     GNU Lesser General Public Licence see LICENCE file or http://www.gnu.org/licenses/lgpl.html
*
* Some parts of this file are took from an experimental version of Copix Framework v2.3dev20050901,
* CopixUrlEngine.significant.class.php,
* copyrighted by CopixTeam and released under GNU Lesser General Public Licence
* author : Laurent Jouanneau
* http://www.copix.org
*/

/**
 * a specific selector for the xml files which contains the configuration of the engine
 * @package  jelix
 * @subpackage core
 */
class jSelectorUrlCfgSig extends jSelectorCfg {
    public $type = 'urlcfgsig';

    public function getCompiler(){
        require_once(JELIX_LIB_CORE_PATH.'url/jUrlCompiler.significant.class.php');
        $o = new jUrlCompilerSignificant();
        return $o;
    }
    public function getCompiledFilePath (){ return JELIX_APP_TEMP_PATH.'compiled/urlsig/creationinfos.php';}
}

/**
 * a specific selector for user url handler
 * @package  jelix
 * @subpackage core
 */
class jSelectorUrlHandler extends jSelectorClass {
    public $type = 'urlhandler';
    protected $_suffix = '.urlhandler.php';
}

/**
 * interface for user url handler
 * @package  jelix
 * @subpackage core
 */
interface jIUrlSignificantHandler {
    /**
    * create the jUrlAction corresponding to the given jUrl. Return false if it doesn't correspond
    * @param jUrl
    * @return jUrlAction|false
    */
    public function parse($url);

    /**
    * fill the given jurl object depending the jUrlAction object
    * @param jUrlAction $urlact
    * @param jUrl $url
    */
    public function create($urlact, $url);
}

/**
 * an url engine to parse,analyse and create significant url
 * it needs an urls.xml file in the config directory (see documentation)
 * @package  jelix
 * @subpackage core
 */
class jUrlEngineSignificant implements jIUrlEngine {

    /**
    * datas to create significant url
    * @var array
    */
    protected $dataCreateUrl = null;

    /**
    * datas to parse and anaylise significant url, and to determine action, module etc..
    * @var array
    */
    protected $dataParseUrl =  null;

    /**
    * Parse some url components
    * @param string $scriptNamePath    /path/index.php
    * @param string $pathinfo          the path info part of the url (part between script name and query)
    * @param array  $params            url parameters (query part e.g. $_REQUEST)
    * @return jUrlAction
    */
    public function parse($scriptNamePath, $pathinfo, $params){
        global $gJConfig;

        $urlact = null;

        if ($gJConfig->urlengine['enableParser']){

            $sel = new jSelectorUrlCfgSig('urls.xml');
            jIncluder::inc($sel);
            $basepath = $GLOBALS['gJConfig']->urlengine['basePath'];
            if(strpos($scriptNamePath, $basepath) === 0){
                $snp = substr($scriptNamePath,strlen($basepath));
            }else{
                $snp = $scriptNamePath;
            }
            $pos = strrpos($snp,$gJConfig->urlengine['entrypointExtension']);
            if($pos !== false){
                $snp = substr($snp,0,$pos);
            }
            $file=JELIX_APP_TEMP_PATH.'compiled/urlsig/'.rawurlencode($snp).'.entrypoint.php';
            if(file_exists($file)){
                require_once($file);
                $this->dataCreateUrl = & $GLOBALS['SIGNIFICANT_CREATEURL']; // fourni via le jIncluder ligne 101
                $this->dataParseUrl = & $GLOBALS['SIGNIFICANT_PARSEURL'][rawurlencode($snp)];
                $urlact = $this->_parse($scriptNamePath, $pathinfo, $params);
                if(!$urlact ){
                    $urlact = new jUrlAction($params);
                }
            }else{
                $urlact = new jUrlAction($params);
            }
        }else{
            $urlact = new jUrlAction($params);
        }
        return $urlact;
    }

    /**
    *
    * @param string $scriptNamePath    /path/index.php
    * @param string $pathinfo          the path info part of the url (part between script name and query)
    * @param array  $params            url parameters (query part e.g. $_REQUEST)
    * @return jUrlAction
    */
    protected function _parse($scriptNamePath, $pathinfo, $params){
        global $gJConfig;

        if(substr($pathinfo,-1) == '/' && $pathinfo != '/'){
                $pathinfo = substr($pathinfo,0,-1);
        }

        $urlact = null;
        $isDefault = false;
        $url = new jUrl($scriptNamePath, $params, $pathinfo);

        foreach($this->dataParseUrl as $k=>$infoparsing){
            // le premier param�tre indique si le point d'entr� actuelle est un point d'entr� par d�faut ou non
            if($k==0){
                $isDefault=$infoparsing;
                continue;
            }

            if(count($infoparsing) < 5){
                // on a un tableau du style
                // array( 0=> 'module', 1=>'action', 2=>'handler', 3=>array('actions','secondaires'))
                $s = new jSelectorUrlHandler($infoparsing[2]);
#ifdef ENABLE_OLD_CLASS_NAMING
                $c =$s->resource.'UrlsHandler';
                if($gJConfig->enableOldClassNaming && !class_exists($c,false)){
                    $c ='URLS'.$s->resource;
                }
#else
                $c =$s->resource.'UrlsHandler';
#endif
                $handler =new $c();

                $url->params['module']=$infoparsing[0];

                // si une action est pr�sente dans l'url actuelle
                // et qu'elle fait partie des actions secondaires, alors on la laisse
                // sinon on prend celle indiqu�e dans la conf
                if( $infoparsing[3]
                    && isset($params['action'])
                    && in_array($params['action'], $infoparsing[3])){

                    $url->params['action']=$params['action']; // action peut avoir �t� �cras� par une it�ration pr�c�dente
                }else{
                    $url->params['action']=$infoparsing[1];
                }
                // appel au handler
                if($urlact = $handler->parse($url)){
                    break;
                }
            }else{
                /* on a un tableau du style
                array( 0=>'module', 1=>'action', 2=>'regexp_pathinfo',
                3=>array('annee','mois'), // tableau des valeurs dynamiques, class�es par ordre croissant
                4=>array(true, false), // tableau des valeurs escapes
                5=>array('bla'=>'cequejeveux' ) // tableau des valeurs statiques
                6=>false ou array('act','act'...) // autres actions secondaires autoris�es
                */
                if(preg_match ($infoparsing[2], $pathinfo, $matches)){
                    if($infoparsing[0] !='')
                        $params['module']=$infoparsing[0];

                    // si une action est pr�sente dans l'url actuelle
                    // et qu'elle fait partie des actions secondaires, alors on la laisse
                    // sinon on prend celle indiqu�e dans la conf
                    if( !($infoparsing[6]
                        && isset($params['action'])
                        && in_array($params['action'], $infoparsing[6]))){

                        if($infoparsing[1] !='')
                            $params['action']=$infoparsing[1];
                    }

                    // on fusionne les parametres statiques
                    if ($infoparsing[5]) {
                        $params = array_merge ($params, $infoparsing[5]);
                    }

                    if(count($matches)){
                        array_shift($matches);
                        foreach($infoparsing[3] as $k=>$name){
                            if(isset($matches[$k])){
                                if($infoparsing[4][$k]){
                                    $params[$name] = jUrl::unescape($matches[$k]);
                                }else{
                                    $params[$name] = $matches[$k];
                                }
                            }
                        }
                    }
                    $urlact = new jUrlAction($params);
                    break;
                }
            }
        }
        if(!$urlact && !$isDefault){
            try{
                $urlact = jUrl::get($gJConfig->urlengine['notfoundAct'],array(),jUrl::JURLACTION);
            }catch(Exception $e){
                $urlact = new jUrlAction(array('module'=>'jelix', 'action'=>'error_notfound'));
            }
        }else if(!$urlact && $isDefault){
            // si on a pas trouver de correspondance, mais que c'est l'entry point
            // par defaut pour le type de request courant, alors on laisse passer..
            $urlact = new jUrlAction($params);
        }

        return $urlact;
    }


    /**
    * Create a jurl object with the given action datas
    * @param jUrlAction $url  information about the action
    * @return jUrl the url correspondant to the action
    */
    public function create( $urlact){

        if($this->dataCreateUrl == null){
            $sel = new jSelectorUrlCfgSig('urls.xml');
            jIncluder::inc($sel);
            $this->dataCreateUrl = & $GLOBALS['SIGNIFICANT_CREATEURL'];
        }

        /*
        a) recupere module~action@request -> obtient les infos pour la creation de l'url
        b) r�cup�re un � un les parametres indiqu�s dans params � partir de jUrl
        c) remplace la valeur r�cup�r�e dans le result et supprime le param�tre de l'url
        d) remplace scriptname de jUrl par le resultat
        */

        $url = new jUrl('',$urlact->params,'');

        $module = $url->getParam('module', jContext::get());
        $action = $url->getParam('action');

        $id = $module.'~'.$action.'@'.$urlact->requestType;
        $urlinfo = null;
        if (isset ($this->dataCreateUrl [$id])){
            $urlinfo = &$this->dataCreateUrl[$id];
            $url->delParam('module');
            $url->delParam('action');
        }else{
            $id = $module.'~*@'.$urlact->requestType;
            if (isset ($this->dataCreateUrl [$id])){
                $urlinfo = &$this->dataCreateUrl[$id];
                $url->delParam('module');
            }else{
                $id = '@'.$urlact->requestType;
                if (isset ($this->dataCreateUrl [$id])){
                    $urlinfo = &$this->dataCreateUrl[$id];
                }else{
                    throw new Exception("Significant url engine doesn't find corresponding url to this action :".$module.'~'.$action.'@'.$urlact->requestType);
                }
            }
        }
        /*
        urlinfo =
            array(0,'entrypoint', https true/false, entrypoint true/false,'handler')
            ou
            array(1,'entrypoint', https true/false, entrypoint true/false
                    array('annee','mois','jour','id','titre'), // liste des param�tres de l'url � prendre en compte
                    array(true, false..), // valeur des escapes
                    "/news/%1/%2/%3/%4-%5", // forme de l'url
                    )
            ou
            array(2,'entrypoint', https true/false, entrypoint true/false); pour les cl�s du type "@request"
            array(3,'entrypoint', https true/false, entrypoint true/false); pour les cl�s du type "module~@request"

        */

        $url->scriptName = $GLOBALS['gJConfig']->urlengine['basePath'].$urlinfo[1];
        if($urlinfo[2])
            $url->scriptName = 'https://'.$_SERVER['HTTP_HOST'].$url->scriptName;

        if($urlinfo[1] && !$GLOBALS['gJConfig']->urlengine['multiview']){
            $url->scriptName.=$GLOBALS['gJConfig']->urlengine['entrypointExtension'];
        }
        // pour certains types de requete, les param�tres ne sont pas dans l'url
        // donc on les supprime
        // c'est un peu crade de faire �a en dur ici, mais ce serait lourdingue
        // de charger la classe request pour savoir si on peut supprimer ou pas
        if(in_array($urlact->requestType ,array('xmlrpc','jsonrpc','soap'))){
            $url->clearParam();
            return $url;
        }

        if($urlinfo[0]==0){
            $s = new jSelectorUrlHandler($urlinfo[3]);
#ifdef ENABLE_OLD_CLASS_NAMING
            $c =$s->resource.'UrlsHandler';
            if($GLOBALS['gJConfig']->enableOldClassNaming && !class_exists($c,false)){
                $c ='URLS'.$s->resource;
            }
#else
            $c =$s->resource.'UrlsHandler';
#endif
            $handler =new $c();
            $handler->create($urlact, $url);
        }elseif($urlinfo[0]==1){
            $result = $urlinfo[5];
            foreach ($urlinfo[3] as $k=>$param){
                if($urlinfo[4][$k]){
                    $result=str_replace(':'.$param, jUrl::escape($url->getParam($param,''),true), $result);
                }else{
                    $result=str_replace(':'.$param, $url->getParam($param,''), $result);
                }
                $url->delParam($param);
            }
            if($urlinfo[1])
                $url->pathInfo = $result;
            else
                $url->pathInfo = substr($result,1);

        }elseif($urlinfo[0]==3){
            $url->delParam('module');
        }

        return $url;
    }
}
?>