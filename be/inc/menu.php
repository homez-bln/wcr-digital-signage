<?php
/**
 * inc/menu.php v7 — Rollen-bewusstes Menü
 */
$_currentScript = basename($_SERVER['PHP_SELF']);
$_currentQuery  = $_SERVER['QUERY_STRING'] ?? '';

$_menuItems = [
    ['Open',           'ctrl/times.php',         'view_times'],
    ['Getränke',       'ctrl/drinks.php',         null],
    ['Essen',          'ctrl/food.php',           null],
    ['Eis',            'ctrl/list.php?t=ice',     null],
    ['Cable',          'ctrl/list.php?t=cable',   null],
    ['Camping',        'ctrl/list.php?t=camping', null],
    ['Extra',          'ctrl/list.php?t=extra',   null],
    ['Kino',           'ctrl/kino.php',           null],
    ['Media',          'ctrl/media.php',          'view_media'],
    ['Obstacles',      'ctrl/obstacles.php',      'view_media'],
    ['DS-Seiten',      'ctrl/ds-seiten.php',      'view_ds'],
    ['DS Controller',  'ctrl/ds-settings.php',    'view_ds'],
];

if (!function_exists('_wcr_menu_active')) {
    function _wcr_menu_active(string $href, string $cur, string $curQ): bool {
        $hrefFile = basename(strtok($href, '?'));
        if ($cur !== $hrefFile) return false;
        $hrefQ = parse_url($href, PHP_URL_QUERY) ?? '';
        if ($hrefQ === '') return true;
        parse_str($hrefQ, $hP);
        parse_str($curQ, $cP);
        return ($hP['t'] ?? '') === ($cP['t'] ?? '');
    }
}
?>
<link rel="stylesheet" href="/be/inc/style.css">
<div class="nav-bar">
  <a href="/be/index.php" <?= $_currentScript === 'index.php' ? 'class="active"' : '' ?>>🏠 Start</a>

  <?php foreach ($_menuItems as [$label, $href, $perm]): ?>
    <?php if ($perm && !wcr_can($perm)) continue; ?>
    <a href="/be/<?= $href ?>"
       class="<?= _wcr_menu_active($href, $_currentScript, $_currentQuery) ? 'active' : '' ?>">
      <?= htmlspecialchars($label) ?>
    </a>
  <?php endforeach; ?>

  <?php if (wcr_can('manage_users')): ?>
    <a href="/be/ctrl/users.php"
       class="<?= $_currentScript === 'users.php' ? 'active' : '' ?>">
      👥 Benutzer
    </a>
  <?php endif; ?>

  <div class="nav-spacer"></div>
  <span class="nav-user"><?= wcr_role_badge() ?></span>
  <a href="/be/logout.php" class="logout">Logout</a>
</div>
