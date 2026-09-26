<?php
defined('APP_ROOT') || exit;

use App\View;

/** @var array $settings  @var bool $authenticated  @var array $bookmarks  @var ?string $error  @var string $return */
$layout = $settings['layout'];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<?php View::render('partials/head', [
    'settings'    => $settings,
    'title'       => $settings['name'],
    'stylesheets' => ['assets/css/base.css', 'assets/css/front.css'],
]) ?>
  <base target="_blank">
</head>
<body>
  <main class="page <?= $authenticated ? 'page--home' : 'page--login' ?>"
        data-logo-position="<?= View::e($layout['logo_position']) ?>"
        data-columns="<?= (int) $layout['columns'] ?>"
        style="--columns: <?= (int) $layout['columns'] ?>">

    <h1 class="sr-only"><?= View::e($settings['name']) ?></h1>

    <aside class="brand">
      <img id="logo" src="<?= View::asset($settings['logo']) ?>" alt="<?= View::e($settings['name']) ?>"
           <?= $settings['logo_animation'] ? 'data-animate' : '' ?>>
    </aside>

<?php if (!$authenticated): ?>
    <section id="login">
      <h2 class="sr-only">Wachtwoord</h2>
      <?php if ($error): ?><p class="error" role="alert"><?= View::e($error) ?></p><?php endif ?>
      <form method="post" action="./" autocomplete="off" target="_self">
        <input type="password" name="password" autofocus placeholder="Wachtwoord" aria-label="Wachtwoord" required>
        <input type="hidden" name="remember" value="1">
        <input type="hidden" name="return" value="<?= View::e($return) ?>">
        <button type="submit" class="sr-only" tabindex="-1">Ontgrendelen</button>
      </form>
    </section>
<?php else: ?>
    <section id="bookmarks">
      <h2 class="sr-only">Favorieten</h2>
<?php foreach (App\Bookmarks::columns($bookmarks, (int) $layout['columns']) as $column): ?>
      <div class="bookmark-column">
        <?php View::bookmarkItems($column, false) ?>
      </div>
<?php endforeach ?>
    </section>
<?php endif ?>
  </main>

<?php if ($authenticated): ?>
  <nav class="corner-links" aria-label="Account">
    <a class="corner-link corner-link--admin" href="./?p=admin" target="_self" title="Instellingen" aria-label="Instellingen">
      <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.49.49 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.48.48 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96a.48.48 0 0 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61zM12 15.6a3.6 3.6 0 1 1 0-7.2 3.6 3.6 0 0 1 0 7.2z"/></svg>
    </a>
    <a class="corner-link corner-link--logout" href="./?p=logout" target="_self" title="Afmelden" aria-label="Afmelden">
      <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
    </a>
  </nav>
<?php endif ?>

<?php if ($settings['logo_animation']): ?>
  <audio id="toing1" src="<?= View::asset('assets/audio/toing1.mp3') ?>" preload="auto"></audio>
  <audio id="toing2" src="<?= View::asset('assets/audio/toing2.mp3') ?>" preload="auto"></audio>
  <script src="<?= View::asset('assets/js/logo.js') ?>" defer></script>
<?php endif ?>
</body>
</html>
