<?php
require_once __DIR__.'/db.php';

function brandFromRequest(){
  try {
    $host = isset($_SERVER['HTTP_HOST']) ? strtolower(preg_replace('/:\d+$/','', $_SERVER['HTTP_HOST'])) : '';
    // tenta por domínio
    if ($host) {
      $s = db()->prepare("SELECT * FROM tenants WHERE domain=? AND is_active=1 LIMIT 1");
      $s->execute([$host]);
      if ($t = $s->fetch()) return normalizeBrand($t);
    }
    // fallback por ?empresa=slug
    $slug = isset($_GET['empresa']) ? trim($_GET['empresa']) : '';
    if ($slug!=='') {
      $s = db()->prepare("SELECT * FROM tenants WHERE slug=? AND is_active=1 LIMIT 1");
      $s->execute([$slug]);
      if ($t = $s->fetch()) return normalizeBrand($t);
    }
  } catch(Exception $e) { /* silencioso */ }
  // padrão
  return [
    'id'=>null,'slug'=>null,'name'=>'Help Fácil',
    'primary'=>'#0d6efd','logo'=>null,'mode'=>'light'
  ];
}

function normalizeBrand($t){
  return [
    'id' => (int)$t['id'],
    'slug' => $t['slug'],
    'name' => $t['name'],
    'primary' => $t['brand_primary'] ?: '#0d6efd',
    'logo' => $t['brand_logo_url'] ?: null,
    'mode' => ($t['brand_mode']==='dark'?'dark':'light'),
  ];
}

function echoBrandStyle($brand){
  $hex = ltrim($brand['primary'], '#');
  if (strlen($hex)!==6) $hex = '0d6efd';
  list($r,$g,$b) = sscanf($hex, "%02x%02x%02x");
  echo '<script>document.documentElement.setAttribute("data-bs-theme","'.($brand['mode']=='dark'?'dark':'light').'");</script>';
  echo "<style>:root{--bs-primary:#$hex;--bs-primary-rgb:$r,$g,$b}</style>";
}
