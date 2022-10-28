<?php

class I18nFrontend {
  
  private static $initialized = false;
  private static $languages = null;
  
  public static function getDefaultLanguage() {
    require_once(GSPLUGINPATH.'i18n_base/basic.class.php');
    return I18nBasic::getProperty(I18N_PROP_DEFAULT_LANGUAGE, I18N_DEFAULT_LANGUAGE);
  }
  
  public static function getLanguages() {
    if (!self::$languages) {
      self::$languages = array();
      if (isset($_GET[I18N_LANGUAGE_PARAM])) {
        self::$languages[] = $_GET[I18N_LANGUAGE_PARAM];
      }
      if (isset($_SESSION[I18N_LANGUAGE_KEY]) && !in_array($_SESSION[I18N_LANGUAGE_KEY],self::$languages)) {
        self::$languages[] = $_SESSION[I18N_LANGUAGE_KEY];
      }
      if (!defined('I18N_IGNORE_USER_LANGUAGE') || !I18N_IGNORE_USER_LANGUAGE) {
        $httplanguages = explode(",", @$_SERVER['HTTP_ACCEPT_LANGUAGE']);
        foreach ($httplanguages as $language) {
          $language = substr($language,0,2);
          if (!in_array($language,self::$languages)) self::$languages[] = $language;
        }
      }
      $deflang = self::getDefaultLanguage();
      if (!in_array($deflang, self::$languages)) self::$languages[] = $deflang;
    }
    return self::$languages;
  }
  
  public static function init() {
    if (self::$initialized) return;
    @session_start();
    if (isset($_GET[I18N_SET_LANGUAGE_PARAM])) {
      $_SESSION[I18N_LANGUAGE_KEY] = $_GET[I18N_SET_LANGUAGE_PARAM];
    }
    $languages = self::getLanguages();
    self::$initialized = true;
    foreach ($languages as $language) {
      if (self::load($language)) return;
    }
  }
  
  public static function getPageData($slug) {
    $languages = self::getLanguages();
    $deflang = self::getDefaultLanguage();
    foreach ($languages as $lang) {
      $file = GSDATAPAGESPATH . $slug . ($lang == $deflang ? '' : '_' . $lang) .'.xml';
      if (file_exists($file)) {
        return getXML($file);
      }
    }
    return null;
  }
  
  public static function getAvailableLanguages($slug=null) {
    $languages = array();
    if (!$slug) $languages[] = self::getDefaultLanguage();
    $dir_handle = @opendir(GSDATAPAGESPATH);
    if ($dir_handle) {
      while ($filename = readdir($dir_handle)) {
        if ($slug) {
          if ($filename == $slug.'.xml') {
            $languages[] = self::getDefaultLanguage();
          } else if (substr($filename,0,strlen($slug)+1) == $slug.'_' && substr($filename,-4) == '.xml') {
            $languages[] = substr($filename,strlen($slug)+1,-4);
          }
        } else if (substr($filename,-4) == '.xml' && ($pos = strrpos($filename,'_')) !== false) {
          $lang = substr($filename,$pos+1,-4);
          if (!in_array($lang,$languages)) $languages[] = $lang;
        }
      }
    }
    return $languages;
  }
  
  public static function outputContent($slug, $force=false) {
    $data = null;
    if ($force) {
      $file = GSDATAPAGESPATH . $slug . '.xml';
      if (file_exists($file)) $data = getXML($file);
    } else {
      $data = self::getPageData($slug);
    }
    if ($data) {
      $content = $data->content;
      $content = stripslashes(htmlspecialchars_decode($content, ENT_QUOTES));
      $content = exec_filter('content',$content);
      echo $content;
      return true;
    } else {
      return false;
    }
  }
  
  public static function getComponent($id) {
    if (file_exists(GSDATAOTHERPATH.'components.xml')) {
      $deflang = self::getDefaultLanguage();
      $data = getXML(GSDATAOTHERPATH.'components.xml');
      $components = $data->item;
      if (count($components) != 0) {
        $languages = self::getLanguages();
        $isV3 = function_exists('get_site_version'); 
        $sep = $isV3 ? '_' : '';
        foreach ($languages as $lang) {
          $slug = $id . ($lang == $deflang ? '' : $sep . $lang);
          foreach ($components as $component) {
            if ($slug == $component->slug) { 
              return stripslashes(htmlspecialchars_decode($component->value, ENT_QUOTES)); 
            }
          }
        }
      }
    }
    return null;
  }
  
  public static function outputComponent($id, $arguments=null) {
    global $args;
    $component = self::getComponent($id);
    if (isset($args)) $saved_args = $args;
    $args = $arguments;
    if ($component) eval("?>" . $component . "<?php ");
    if (isset($saved_args)) $args = $saved_args; else unset($args); 
    return $component ? true : false;
  }
  
  public static function getPageURL() {
    global $url, $parent;
    return self::getURL($url, $parent, @$_GET[I18N_LANGUAGE_PARAM]);
  }
  
  public static function getURL($slug, $slugparent, $language=null, $type='full') {
    global $url, $parent, $PERMALINK, $PERMALINK_ORIG;
    if (!isset($PERMALINK_ORIG)) $PERMALINK_ORIG = $PERMALINK;
    if (!$slug) {
      $slug = @$url;
      $slugparent = @$parent;
    }
    if (@strpos(@$PERMALINK_ORIG,'%language%') !== false || 
        @strpos(@$PERMALINK_ORIG,'%nondefaultlanguage%') !== false) {
      $u = self::getFancyLanguageUrl($slug, $slugparent, $language, $type);
    } else if ($language && defined('I18N_SEPARATOR')) {
      $u = find_url($slug, $slugparent, $type);
      if ($slug == 'index') {
        $u .= I18N_SEPARATOR.$language;
      } else {
        preg_match('/^([^\?]*[^\?\/])(\/?(\?.*)?)$/', $u, $match);
        $u = $match[1].I18N_SEPARATOR.$language.@$match[2];
      }
    } else { 
      $u = find_url($slug, $slugparent, $type);
      if ($language) {
        $u .= (strpos($u,'?') !== false ? '&' : '?') . I18N_LANGUAGE_PARAM . '=' . $language;
      }
    }
    return $u;
  }

  public static function getLangURL($language=null) {
    global $url, $parent;
    return self::getURL($url, $parent, $language);
  }

  public static function getSetLangURL($language) {
    global $url, $parent, $PERMALINK_ORIG;
    if (@strpos(@$PERMALINK_ORIG,'%language%') !== false ||
        @strpos(@$PERMALINK_ORIG,'%nondefaultlanguage%') !== false) {
      return self::getURL($url,$parent,$language); // no setlang parameter supported for URLs with language
    } else {
      $u = self::getURL($url,$parent);
      $u .= (strpos($u,'?') !== false ? '&' : '?') . I18N_SET_LANGUAGE_PARAM . '=' . $language;
      return $u;
    }
  }

  public static function outputLinkTo($slug) {
    $data = self::getPageData($slug);
    if (!$data) return false;
    echo '<a href="'.find_url($slug,(string) $data->parent).'">'.stripslashes((string) $data->title).'</a>';
    return true;
  }

  // like get_header, but tags beginning with _ are ignored and the language is appended to the canonical URL
  public static function outputHeader($full=true) {
    global $metad, $metak, $title, $content, $url, $parent, $language;
    include(GSADMININCPATH.'configuration.php');
    if ($metad != '') {
      $description = stripslashes(htmlspecialchars_decode($metad, ENT_QUOTES));
    } else {
      if (function_exists('mb_substr')) { 
        $description = trim(mb_substr(html_entity_decode(strip_tags(stripslashes(htmlspecialchars_decode($content, ENT_QUOTES))),ENT_QUOTES, 'UTF-8'), 0, 160));
      } else {
        $description = trim(substr(html_entity_decode(strip_tags(stripslashes(htmlspecialchars_decode($content, ENT_QUOTES))),ENT_QUOTES, 'UTF-8'), 0, 160));
      }
      $description = str_replace('"','', $description);
      $description = str_replace("'",'', $description);
      $description = preg_replace('/\n/', " ", $description);
      $description = preg_replace('/\r/', " ", $description);
      $description = preg_replace('/\t/', " ", $description);
      $description = preg_replace('/ +/', " ", $description);
    }
    $keywords = array();
    $tags = preg_split("/\s*,\s*/", stripslashes(htmlspecialchars_decode($metak, ENT_QUOTES)));
    if (count($tags) > 0) foreach ($tags as $tag) if (substr(trim($tag),0,1) != '_') $keywords[] = trim($tag);
    
    echo '<meta name="description" content="'.htmlspecialchars(trim($description)).'" />'."\n";
    echo '<meta name="keywords" content="'.htmlspecialchars(implode(', ',$keywords)).'" />'."\n";
    if ($full) {
      echo '<meta name="generator" content="'.$site_full_name.'" />'."\n";
      echo '<link rel="canonical" href="'.find_i18n_url($url,$parent,$language).'" />'."\n";
    }
    exec_action('theme-header');
  }

  private static function load($lang) {
    global $language, $data_index_orig;
    global $data_index, $url, $title, $date, $metak, $metad, $content, $parent, $template_file, $private;
    global $PERMALINK, $PERMALINK_ORIG;
    $deflang = self::getDefaultLanguage();
    if ($lang == $deflang) {
      $language = $deflang;
    } else { 
      // load language
      $file = GSDATAPAGESPATH . $url . '_' . $lang .'.xml';
      if (file_exists($file)) {
        $data = getXML($file);
        // $url stays the same
        $title = $data->title;
        $date = $data->pubDate;
        $content = $data->content;
        // $parent stays the same
        // only overwrite if not empty:
        if ((string) $data->meta) $metak = $data->meta;
        if ((string) $data->metad) $metad = $data->metad;
        // $template stays the same
        // $private stays the same
        $language = $lang;
        $data_index_orig = $data_index;
        $data_index = $data;
      } else {
        return false;
      }
    }
    if (@$PERMALINK) {
      $PERMALINK_ORIG = $PERMALINK;
      $l = @$_GET['lang'] ? $_GET['lang'] : $language;
      if ($l != $deflang) {
        $PERMALINK = str_replace('%nondefaultlanguage%', $l, $PERMALINK);
      } else {
        $PERMALINK = str_replace('%nondefaultlanguage%/', '', $PERMALINK);
        $PERMALINK = str_replace('%nondefaultlanguage%', '', $PERMALINK);
      }
      $PERMALINK = str_replace('%language%', $l, $PERMALINK);
    }
    return true;
  }
  
  private static function getFancyLanguageUrl($slug, $parent, $lang=null, $type='full') {
    global $SITEURL, $PERMALINK, $PERMALINK_ORIG, $language;
    if (!isset($PERMALINK_ORIG)) $PERMALINK_ORIG = $PERMALINK;
    if ($type == 'full') {
      $full = $SITEURL;
    } else {
      $full = '/';
    }

    if (!$lang) {
      $lang = @$_GET[I18N_LANGUAGE_PARAM] ? $_GET[I18N_LANGUAGE_PARAM] : $language;
    }
    $plink = $PERMALINK_ORIG;
    if ($lang != self::getDefaultLanguage()) {
      $plink = str_replace('%nondefaultlanguage%', $lang, $plink);
    } else {
      $plink = str_replace('%nondefaultlanguage%/', '', $plink);
      $plink = str_replace('%nondefaultlanguage%', '', $plink);
    }
    $plink = str_replace('%language%', $lang, $plink);
    if ((string) $parent) {
      //echo "===$parent===";
      $plink = str_replace('%parent%', $parent, $plink);
    } else {
      $plink = str_replace('%parent%/', '', $plink);
      $plink = str_replace('%parent%', '', $plink);
    }
    if ((string) $slug && $slug != 'index') {
      $plink = str_replace('%slug%', $slug, $plink);
    } else {
      $plink = str_replace('%slug%/', '', $plink);
      $plink = str_replace('%slug%', '', $plink);
    }
    return (string) $full . $plink;
  }
  
  
}
