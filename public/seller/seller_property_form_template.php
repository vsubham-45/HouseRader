<?php
// public/admin-panel/property_form_template.php
// Reusable property form template (dynamic config UI). Expects $prop, $errors, $success, $action, $submitLabel
if (!isset($prop) || !is_array($prop)) $prop = [];
$action = $action ?? ($_SERVER['PHP_SELF'] . (isset($_GET['id']) ? ('?id=' . (int)$_GET['id']) : ''));
$submitLabel = $submitLabel ?? 'Save';
$errors = $errors ?? [];
$success = $success ?? null;

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['property_csrf'])) $_SESSION['property_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['property_csrf'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function val($k,$d=''){ global $prop; return array_key_exists($k,$prop) ? $prop[$k] : $d; }
function img_preview_tag($src){ if(!$src) return ''; $safe=h($src); return '<div style="margin-top:6px;"><img src="'.$safe.'" alt="" style="max-width:180px; max-height:110px; object-fit:cover; border-radius:6px; border:1px solid rgba(0,0,0,0.06);" onerror="this.src=\'../assets/img/placeholder.png\'" /></div>'; }

$configKeys = ['RK','1BHK','2BHK','3BHK','4BHK','5BHK'];
// Try to normalize existing configs if provided in $prop['configs'] (JSON) or $prop['configurations'] (CSV/JSON)
$existingConfigs = [];
$existingMeta = [];
if (!empty($prop['configs']) && is_string($prop['configs'])) {
    $maybe = @json_decode($prop['configs'], true);
    if (is_array($maybe)) {
        foreach ($maybe as $k => $v) {
            if (is_int($k)) { $existingConfigs[] = (string)$v; } else { $existingConfigs[] = (string)$k; if (is_array($v)) $existingMeta[$k]=$v; }
        }
    }
} elseif (!empty($prop['configs']) && is_array($prop['configs'])) {
    foreach ($prop['configs'] as $k=>$v){ $existingConfigs[]=$k; if (is_array($v)) $existingMeta[$k]=$v; }
} elseif (!empty($prop['configurations'])) {
    $raw = $prop['configurations'];
    $maybe = @json_decode($raw, true);
    if (is_array($maybe)) {
        foreach ($maybe as $k=>$v){ if (is_int($k)) $existingConfigs[]=(string)$v; else{ $existingConfigs[]=(string)$k; if(is_array($v)) $existingMeta[$k]=$v; } }
    } else {
        $parts = array_filter(array_map('trim', explode(',', $raw)));
        foreach ($parts as $p) $existingConfigs[] = $p;
    }
}
$existingConfigs = array_values(array_unique($existingConfigs));

function existing_meta_val($k,$field,$d=''){ global $existingMeta; if (!empty($_POST['configs'][$k][$field])) return $_POST['configs'][$k][$field]; if (!empty($existingMeta[$k][$field])) return $existingMeta[$k][$field]; return $d; }
?>
<div class="card">
  <?php if(!empty($errors)): ?>
    <div style="margin-bottom:12px; background:#fdecea; color:#b00020; padding:10px 12px; border-radius:8px;">
      <strong>Errors:</strong>
      <ul style="margin:8px 0 0 18px;"><?php foreach($errors as $e): ?><li><?php echo h($e); ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>
  <?php if($success): ?><div style="margin-bottom:12px; background:#eafaf1; color:#117a3a; padding:10px 12px; border-radius:8px;"><?php echo h($success); ?></div><?php endif; ?>

  <form id="property-form" action="<?php echo h($action); ?>" method="post" enctype="multipart/form-data" style="background:var(--card); padding:16px; border-radius:10px;">
    <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>" />
    <?php if (!empty($prop['id'])): ?><input type="hidden" name="id" value="<?php echo (int)$prop['id']; ?>" /><?php endif; ?>

    <div style="display:flex;gap:12px;flex-wrap:wrap;">
      <div style="flex:1;min-width:260px;">
        <label>Title *</label>
        <input name="title" required type="text" value="<?php echo h(val('title')); ?>" style="width:100%;padding:10px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);" />
      </div>

      <div style="width:180px;">
        <label>City *</label>
        <select name="city" required style="width:100%;padding:10px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);">
          <option value="">Select city</option>
          <?php foreach(['Mumbai','Navi-Mumbai','Thane'] as $c): ?><option value="<?php echo h($c); ?>" <?php if(val('city')===$c) echo 'selected'; ?>><?php echo h($c); ?></option><?php endforeach; ?>
        </select>
      </div>

      <div style="width:180px;">
        <label>Locality</label>
        <input name="locality" type="text" value="<?php echo h(val('locality')); ?>" placeholder="Neighborhood / Locality" style="width:100%;padding:10px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);" />
      </div>

      <div style="flex:1;min-width:260px;">
        <label>Address</label>
        <input name="address" type="text" value="<?php echo h(val('address')); ?>" placeholder="Full address (flat / building / street / landmark)" style="width:100%;padding:10px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);" />
      </div>

      <div style="width:240px;">
        <label>Property Type *</label>
        <?php $ptype = val('property_type','sale'); ?>
        <select id="property-type" name="property_type" required style="width:100%;padding:10px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);">
          <option value="upcoming" <?php if($ptype==='upcoming') echo 'selected'; ?>>Upcoming</option>
          <option value="sale" <?php if($ptype==='sale') echo 'selected'; ?>>For Sale</option>
          <option value="rental" <?php if($ptype==='rental') echo 'selected'; ?>>Rental</option>
        </select>
      </div>
    </div>

    <!-- Rental Type: visible only when Property Type == rental -->
    <div id="rental-type-container" style="margin-top:10px; display:<?php echo ($ptype==='rental') ? 'block' : 'none'; ?>;">
      <label>Rental Type</label>
      <?php $rental_type_val = val('rental_type','rental'); // default to 'rental' ?>
      <select id="rental-type" name="rental_type" style="padding:10px;border-radius:8px;border:1px solid rgba(0,0,0,0.08); width:220px;">
        <option value="rental" <?php if($rental_type_val==='rental') echo 'selected'; ?>>Normal</option>
        <option value="pg" <?php if($rental_type_val==='pg') echo 'selected'; ?>>PG</option>
      </select>
    </div>

    <div style="display:flex;gap:12px;margin-top:12px;flex-wrap:wrap;">
      <div style="flex:1;min-width:200px;">
        <label>Owner Name *</label>
        <input name="owner_name" required type="text" value="<?php echo h(val('owner_name')); ?>" style="width:100%;padding:10px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);" />
      </div>
      <div style="width:220px;">
        <label>Owner Phone *</label>
        <input name="owner_phone" required type="tel" value="<?php echo h(val('owner_phone')); ?>" style="width:100%;padding:10px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);" />
      </div>
      <div style="flex:1;min-width:220px;">
        <label>Owner Email</label>
        <input name="owner_email" type="email" value="<?php echo h(val('owner_email')); ?>" style="width:100%;padding:10px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);" />
      </div>
    </div>

    <!-- Config selection -->
    <div style="margin-top:12px;">
      <label>Configuration</label>
      <div id="config-checkboxes" style="margin-top:8px;">
        <?php foreach($configKeys as $k): ?>
          <label style="display:inline-flex;align-items:center;gap:8px;margin-right:8px;">
            <input type="checkbox" class="config-checkbox" data-key="<?php echo h($k); ?>" name="configurations[]" value="<?php echo h($k); ?>" <?php if(in_array($k,$existingConfigs,true)) echo 'checked'; ?> />
            <span style="font-weight:600;"><?php echo h($k); ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div id="config-rental-select" style="display:<?php echo ($ptype==='rental') ? 'block' : 'none'; ?>;margin-top:8px;">
        <select id="rental-config" name="rental_config" style="padding:8px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);">
          <option value="">Choose configuration (rental)</option>
          <?php foreach($configKeys as $k): ?>
            <option value="<?php echo h($k); ?>" <?php if(in_array($k,$existingConfigs,true) && count($existingConfigs)===1) echo 'selected'; ?>><?php echo h($k); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="config-meta-container" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;"></div>
    </div>

    <div style="display:flex;gap:12px;margin-top:12px;align-items:flex-start;flex-wrap:wrap;">
      <div style="width:260px;">
        <label>Furnishing</label>
        <select name="furnishing" style="width:100%;padding:10px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);">
          <option value="">Select</option>
          <?php foreach(['Fully Furnished','Semi Furnished','Not Furnished'] as $f): ?><option value="<?php echo h($f); ?>" <?php if(val('furnishing')===$f) echo 'selected'; ?>><?php echo h($f); ?></option><?php endforeach; ?>
        </select>
      </div>

      <div style="flex:1;">
        <label>Amenities</label>
        <input name="amenities" type="text" value="<?php echo h(val('amenities')); ?>" placeholder="Comma separated" style="width:100%;padding:10px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);" />
      </div>
    </div>

    <div style="margin-top:12px;">
      <label>Short description</label>
      <textarea name="description" rows="4" style="width:100%;padding:10px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);"><?php echo h(val('description')); ?></textarea>
    </div>

    <div style="display:flex;gap:12px;margin-top:12px;flex-wrap:wrap;">
      <div style="width:180px;">
        <label>Latitude</label>
        <input name="latitude" type="text" value="<?php echo h(val('latitude')); ?>" style="width:100%;padding:8px;border-radius:8px;border:1px solid rgba(0,0,0,0.06);" />
      </div>
      <div style="width:180px;">
        <label>Longitude</label>
        <input name="longitude" type="text" value="<?php echo h(val('longitude')); ?>" style="width:100%;padding:8px;border-radius:8px;border:1px solid rgba(0,0,0,0.06);" />
      </div>
    </div>

    <div style="display:flex;gap:12px;margin-top:12px;flex-wrap:wrap;align-items:flex-start;">
      <?php for($i=1;$i<=4;$i++): $k='img'.$i; $img=val($k); ?>
        <div style="width:calc(25% - 9px);min-width:160px;">
          <label>Image <?php echo $i; ?></label>
          <input name="img_url_<?php echo $i; ?>" type="text" placeholder="Image URL (optional)" value="<?php echo h($img); ?>" style="width:100%;padding:8px;border-radius:8px;border:1px solid rgba(0,0,0,0.06);" />
          <div style="margin:8px 0;text-align:center;">or</div>
          <input name="img_file_<?php echo $i; ?>" type="file" accept="image/*" style="width:100%;" />
          <?php echo img_preview_tag($img); ?>
        </div>
      <?php endfor; ?>
    </div>

    <div style="margin-top:16px;display:flex;gap:12px;align-items:center;justify-content:flex-end;">
      <a href="seller_index.php" class="btn" style="text-decoration:none;color:#555;background:transparent;border:1px solid rgba(0,0,0,0.06);padding:10px 12px;border-radius:8px;">Cancel</a>
      <button type="submit" id="submit-btn" class="btn add-btn" style="padding:10px 16px;"><?php echo h($submitLabel); ?></button>
    </div>
  </form>
</div>

<script>
(function(){
  const configKeys = <?php echo json_encode($configKeys); ?>;
  const existingConfigs = <?php echo json_encode($existingConfigs); ?>;
  const existingMeta = <?php echo json_encode($existingMeta); ?>;
  const ptype = document.getElementById('property-type');
  const boxes = document.querySelectorAll('.config-checkbox');
  const rentalSelectContainer = document.getElementById('config-rental-select');
  const rentalSelect = document.getElementById('rental-config');
  const rentalTypeContainer = document.getElementById('rental-type-container');
  const rentalTypeSelect = document.getElementById('rental-type');
  const metaContainer = document.getElementById('config-meta-container');
  const MAX = 6, MIN = 1;

  function createMeta(key, mode){
    const div = document.createElement('div');
    div.style.minWidth = '240px';
    div.style.padding = '8px';
    div.style.borderRadius = '8px';
    div.style.border = '1px dashed rgba(0,0,0,0.06)';
    div.style.background = 'var(--card)';
    const t = document.createElement('div'); t.style.fontWeight='700'; t.style.marginBottom='6px'; t.textContent=key; div.appendChild(t);
    if(mode==='rental'){
      const rent = document.createElement('input'); rent.name=`configs[${key}][rent]`; rent.type='number'; rent.step='0.01'; rent.placeholder='₹ rent'; rent.value = existingMeta[key] && existingMeta[key].rent ? existingMeta[key].rent : ''; rent.style.marginRight='6px'; rent.style.padding='8px'; rent.style.borderRadius='6px'; rent.style.border='1px solid rgba(0,0,0,0.08)'; div.appendChild(rent);
      const carpet = document.createElement('input'); carpet.name=`configs[${key}][carpet]`; carpet.placeholder='Carpet (sq.ft)'; carpet.value = existingMeta[key] && existingMeta[key].carpet ? existingMeta[key].carpet : ''; carpet.style.padding='8px'; carpet.style.borderRadius='6px'; carpet.style.border='1px solid rgba(0,0,0,0.08)'; div.appendChild(carpet);
    } else {
      const price = document.createElement('input'); price.name=`configs[${key}][price]`; price.type='number'; price.step='0.01'; price.placeholder='₹ price'; price.value= existingMeta[key] && existingMeta[key].price ? existingMeta[key].price : ''; price.style.marginRight='6px'; price.style.padding='8px'; price.style.borderRadius='6px'; price.style.border='1px solid rgba(0,0,0,0.08)'; div.appendChild(price);
      const built = document.createElement('input'); built.name=`configs[${key}][built_up]`; built.placeholder='Built-up (sq.ft)'; built.value = existingMeta[key] && existingMeta[key].built_up ? existingMeta[key].built_up : ''; built.style.marginRight='6px'; built.style.padding='8px'; built.style.borderRadius='6px'; built.style.border='1px solid rgba(0,0,0,0.08)'; div.appendChild(built);
      const carpet = document.createElement('input'); carpet.name=`configs[${key}][carpet]`; carpet.placeholder='Carpet (sq.ft)'; carpet.value = existingMeta[key] && existingMeta[key].carpet ? existingMeta[key].carpet : ''; carpet.style.padding='8px'; carpet.style.borderRadius='6px'; carpet.style.border='1px solid rgba(0,0,0,0.08)'; div.appendChild(carpet);
    }
    div.dataset.key = key;
    return div;
  }

  function rebuild(){
    metaContainer.innerHTML = '';
    const mode = ptype.value;
    if(mode==='rental'){
      rentalSelectContainer.style.display='block';
      if(rentalTypeContainer) rentalTypeContainer.style.display='block';
      document.getElementById('config-checkboxes').style.display='none';
      if(rentalSelect && rentalSelect.value) metaContainer.appendChild(createMeta(rentalSelect.value,'rental'));
    } else {
      rentalSelectContainer.style.display='none';
      if(rentalTypeContainer) rentalTypeContainer.style.display='none';
      document.getElementById('config-checkboxes').style.display='block';
      const checked = Array.from(document.querySelectorAll('.config-checkbox:checked')).map(i=>i.dataset.key);
      checked.forEach(k => metaContainer.appendChild(createMeta(k,mode)));
    }
    enforceLimits();
  }

  function enforceLimits(){
    const checked = document.querySelectorAll('.config-checkbox:checked').length;
    if(checked>=MAX) document.querySelectorAll('.config-checkbox:not(:checked)').forEach(c=>c.disabled=true); else document.querySelectorAll('.config-checkbox').forEach(c=>c.disabled=false);
  }

  boxes.forEach(b=>b.addEventListener('change', ()=>{ enforceLimits(); rebuild(); }));
  rentalSelect && rentalSelect.addEventListener('change', rebuild);
  ptype.addEventListener('change', rebuild);

  // init
  (function init(){
    enforceLimits();
    rebuild();
  })();

  // client side validation
  document.getElementById('property-form').addEventListener('submit', function(e){
    const mode = ptype.value;
    if(mode==='rental'){
      if(!rentalSelect.value){ e.preventDefault(); alert('Select a configuration for rental.'); return; }
      const rent = document.querySelector(`[name="configs[${rentalSelect.value}][rent]"]`);
      const carpet = document.querySelector(`[name="configs[${rentalSelect.value}][carpet]"]`);
      if(!rent || rent.value.trim()===''){ e.preventDefault(); alert('Enter rent for selected configuration.'); return; }
      if(!carpet || carpet.value.trim()===''){ e.preventDefault(); alert('Enter carpet area for selected configuration.'); return; }
      // ensure rental type selected
      if(rentalTypeSelect && (!rentalTypeSelect.value || rentalTypeSelect.value==='')) { e.preventDefault(); alert('Select rental type (Normal or PG).'); return; }
    } else {
      const checked = document.querySelectorAll('.config-checkbox:checked').length;
      if(checked < MIN){ e.preventDefault(); alert('Select at least one configuration.'); return; }
      // ensure each chosen has price
      for(const cb of document.querySelectorAll('.config-checkbox:checked')){
        const k = cb.dataset.key;
        const price = document.querySelector(`[name="configs[${k}][price]"]`);
        if(!price || price.value.trim()===''){ e.preventDefault(); alert('Enter price for ' + k); return; }
      }
    }
    // require title & owner phone
    const title = document.querySelector('[name="title"]').value.trim();
    const phone = document.querySelector('[name="owner_phone"]').value.trim();
    if(!title){ e.preventDefault(); alert('Title is required.'); return; }
    if(!phone){ e.preventDefault(); alert('Owner phone is required.'); return; }
  });
})();
</script>
