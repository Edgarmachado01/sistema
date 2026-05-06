<?php
require_once __DIR__.'/db.php';

function brandFromRequest(){
  try {
    $host = isset($_SERVER['HTTP_HOST']) ? strtolower(preg_replace('/:\d+$/','', $_SERVER['HTTP_HOST'])) : '';
    // tenta por domínio
    if ($host) {
      $s = db()->prepare("SELECT * FROM tenants WHERE domain=? AND is_active=1 LIMIT 1");
      $s->execute([$host]);
      if ($t = $s->fetch()) return mergeTenantConfigBrand(normalizeBrand($t));
    }
    // fallback por ?empresa=slug
    $slug = isset($_GET['empresa']) ? trim($_GET['empresa']) : '';
    if ($slug!=='') {
      $s = db()->prepare("SELECT * FROM tenants WHERE slug=? AND is_active=1 LIMIT 1");
      $s->execute([$slug]);
      if ($t = $s->fetch()) return mergeTenantConfigBrand(normalizeBrand($t));
    }
  } catch(Exception $e) {
    error_log('brand.php brandFromRequest: '.$e->getMessage());
  }
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

function mergeTenantConfigBrand($brand){
  $tenantId = (int)($brand['id'] ?? 0);
  if ($tenantId <= 0) {
    return $brand;
  }

  try {
    $s = db()->prepare("
      SELECT nome_fantasia, logo_path, cor_primaria
      FROM tenant_config
      WHERE tenant_id = ?
      LIMIT 1
    ");
    $s->execute([$tenantId]);
    $cfg = $s->fetch();
    if (!$cfg) {
      return $brand;
    }

    if (!empty($cfg['nome_fantasia'])) {
      $brand['name'] = $cfg['nome_fantasia'];
    }
    if (!empty($cfg['logo_path'])) {
      $brand['logo'] = $cfg['logo_path'];
    }
    if (!empty($cfg['cor_primaria'])) {
      $brand['primary'] = $cfg['cor_primaria'];
    }
  } catch(Exception $e) {
    error_log('brand.php tenant_config: '.$e->getMessage());
  }

  return $brand;
}

function echoBrandStyle($brand){
  $hex = ltrim($brand['primary'], '#');
  if (strlen($hex)!==6) $hex = '0d6efd';
  list($r,$g,$b) = sscanf($hex, "%02x%02x%02x");
  echo '<script>document.documentElement.setAttribute("data-bs-theme","'.($brand['mode']=='dark'?'dark':'light').'");</script>';
  echo "<style>:root{--bs-primary:#$hex;--bs-primary-rgb:$r,$g,$b}</style>";
}