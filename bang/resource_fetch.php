<?php
if (count(get_included_files()) == 1) define ('TEST_SUITE', __FILE__);

require_once 'UnityAsset.php';

$resourceToExport = [
  [ 'bundleNameMatch'=>'/^characters\/resourceset\/res(\d+)$/', 'namePrefix'=>'$1_', 'nameMatch'=>'/^(.+)$/',     'exportTo'=>'card/$1' ],
  [ 'bundleNameMatch'=>'/^title\/(.+)$/', 'namePrefix'=>'$1_', 'nameMatch'=>'/^(.+?)_title_bg$/',     'exportTo'=>'title_bg/$1' ]
];

function shouldExportFile($name, $rule) {
  return preg_match($rule['nameMatch'], $name) != 0;
}

function shouldUpdate($name, $hash) {
  $cacheHash = file_exists('cacheHash.json') ? json_decode(file_get_contents('cacheHash.json'), true) : [];
  return !(isset($cacheHash[$name]) && ($cacheHash[$name] === $hash));
}
function setHashCached($name, $hash) {
  $cacheHash = file_exists('cacheHash.json') ? json_decode(file_get_contents('cacheHash.json'), true) : [];
  $cacheHash[$name] = $hash;
  file_put_contents('cacheHash.json', json_encode($cacheHash, JSON_UNESCAPED_SLASHES));
}

function findRule($name, $rules) {
  foreach ($rules as $rule) {
    if (preg_match($rule['bundleNameMatch'], $name) != 0) return $rule;
  }
  return false;
}

define('RESOURCE_PATH_PREFIX', '/data/home/web/_redive/bang/');
//define('RESOURCE_PATH_PREFIX', 'D:/cygwin64/home/ester/quickcode/bang/img/');

function checkAndUpdateResource($dataVer) {
  global $resourceToExport;
  global $curl;
  chdir(__DIR__);
  $currenttime = time();
  curl_setopt_array($curl, array(
    CURLOPT_CONNECTTIMEOUT=>5,
    CURLOPT_ENCODING=>'gzip',
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HEADER=>0,
    CURLOPT_SSL_VERIFYPEER=>false
  ));
  $manifest = json_decode(file_get_contents('data/AssetBundleInfo.json'), true);

  foreach ($manifest['bundles'] as $name => $info) {
    if (($rule = findRule($name, $resourceToExport)) !== false && shouldUpdate($name, $info['crc'])) {
      _log('download '. $name);
      curl_setopt_array($curl, array(
        CURLOPT_URL=>'https://d2ktlshvcuasnf.cloudfront.net/Release/'.$dataVer.'/iOS/'.$info['bundleName'],
      ));
      $bundleData = curl_exec($curl);
      if (strlen($bundleData) != $info['fileSize']) {
        _log('download failed  '.$name);
        continue;
      }
      $bundleData = new MemoryStream($bundleData);
      $assets = extractBundle($bundleData);

      try{
      
      foreach ($assets as $asset) {
        if (substr($asset, -4,4) == '.resS') continue;
        $asset = new AssetFile($asset);
    
        foreach ($asset->preloadTable as &$item) {
          if ($item->typeString == 'Texture2D') {
            $item = new Texture2D($item, true);
            $itemname = $item->name;
            if (isset($rule['namePrefix'])) {
              $itemname = preg_replace($rule['bundleNameMatch'], $rule['namePrefix'], $name).$itemname;
            }
            if (isset($rule['print'])) {
              var_dump($itemname);
              continue;
            }
            if (shouldExportFile($itemname, $rule)) {
              $saveTo = RESOURCE_PATH_PREFIX. preg_replace($rule['nameMatch'], $rule['exportTo'], $itemname);
              $item->exportTo($saveTo, 'webp', '-lossless 1');
              touch($saveTo. '.webp', $currenttime);
            }
            unset($item);
          }
        }
        $asset->__desctruct();
        unset($asset);
        gc_collect_cycles();
      }

      } catch(Exception $e) {
        $asset->__desctruct();
        unset($asset);
        _log('Not supported: '. $e->getMessage());
      }

      foreach ($assets as $asset) {
        unlink($asset);
      }
      unset($bundleData);
      //if (isset($rule['print'])) exit;
      setHashCached($name, $info['crc']);
    }
  }
}

if (defined('TEST_SUITE') && TEST_SUITE == __FILE__) {
  chdir(__DIR__);
  $curl = curl_init();
  function _log($s) {echo "$s\n";}
  var_dump(trim(file_get_contents('data/!dataVersion.txt')));
  checkAndUpdateResource(trim(file_get_contents('data/!dataVersion.txt')));
  /*$asset = new AssetFile('CAB-4856cccde53d6f3bfd0054253f1639a8');
  foreach ($asset->preloadTable as &$item) {
    if ($item->typeString == 'Texture2D') {
      $item = new Texture2D($item, true);
      _log($item->name);
      $item->exportTo($item->name, 'webp', '-lossless 1');
    }
  }*/
}
//print_r($asset);

