<?php
defined('APP_ROOT') || exit;

use App\View;

/** @var array $settings  @var array $boot */
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<?php View::render('partials/head', [
    'settings'    => $settings,
    'title'       => 'Beheer — ' . $settings['name'],
    'stylesheets' => ['assets/css/base.css', 'assets/css/admin.css'],
]) ?>
</head>
<body>
<div class="admin">
  <header class="toolbar">
    <h1>Beheer</h1>
    <div class="links">
      <span id="status" class="hidden" role="status"></span>
      <a class="back" href="./">← Naar site</a>
      <a class="back" href="./?p=logout">Uitloggen</a>
    </div>
  </header>

  <nav class="tabs" role="tablist">
    <button type="button" role="tab" data-tab="bookmarks">Bookmarks</button>
    <button type="button" role="tab" data-tab="icons">Icons</button>
    <button type="button" role="tab" data-tab="design">Vormgeving</button>
    <button type="button" role="tab" data-tab="settings">Instellingen</button>
  </nav>

  <!-- Bookmarks -->
  <section id="tab-bookmarks" class="tab-panel" role="tabpanel" hidden>
    <div class="toolbar">
      <div class="links">
        <button type="button" id="add-root-category" class="primary">+ Categorie</button>
        <button type="button" id="add-root-link" class="primary">+ Bookmark</button>
      </div>
      <button type="button" id="reload" class="small">Herladen</button>
    </div>
    <p class="hint">Sleep items om ze te verplaatsen, naar een andere kolom te zetten of in een categorie te plaatsen. Wijzigingen worden direct opgeslagen.</p>
    <div id="tree-root"></div>
  </section>

  <!-- Icons -->
  <section id="tab-icons" class="tab-panel" role="tabpanel" hidden>
    <div class="toolbar">
      <input type="search" id="icons-search" placeholder="Zoeken…" aria-label="Icons zoeken">
      <label class="button primary">
        + Uploaden
        <input type="file" id="icons-upload" accept=".png,.jpg,.jpeg,.gif,.webp,.svg,.ico" multiple hidden>
      </label>
    </div>
    <div id="icons-drop" class="dropzone">
      <p class="hint"><span id="icons-count"></span> Sleep afbeeldingen hierheen om ze te uploaden (png, jpg, gif, webp, svg, ico — max. 2 MB).</p>
      <div id="icons-grid" class="icon-grid icon-grid--manage"></div>
    </div>
  </section>

  <!-- Vormgeving: velden horen via form="design-form" bij het formulier op het tabblad Instellingen -->
  <section id="tab-design" class="tab-panel design-form" role="tabpanel" hidden>
    <fieldset>
      <legend>Achtergrond</legend>
      <div class="choice-group">
        <span class="choice-label">Soort</span>
        <label class="choice"><input type="radio" name="background_mode" value="image" form="design-form"> Eigen afbeelding</label>
        <label class="choice"><input type="radio" name="background_mode" value="pattern" form="design-form"> Patroon</label>
        <label class="choice"><input type="radio" name="background_mode" value="color" form="design-form"> Solide kleur</label>
      </div>

      <div class="bg-mode-panel" data-mode="image">
        <div class="asset-field" data-field="background_image" data-slot="background">
          <span class="asset-label">Afbeelding</span>
          <div class="asset-preview asset-preview--wide"><img alt=""><span class="asset-empty">geen afbeelding</span></div>
          <div class="asset-actions">
            <label class="button small">Uploaden…<input type="file" accept=".png,.jpg,.jpeg,.gif,.webp,.svg" hidden></label>
            <button type="button" class="small" data-reset>Standaard (golven)</button>
          </div>
        </div>
        <p class="hint">De afbeelding vult het hele scherm en blijft staan tijdens het scrollen.</p>
      </div>

      <div class="bg-mode-panel" data-mode="pattern">
        <div id="bg-variants" class="bg-variants" role="radiogroup" aria-label="Patroon"></div>
      </div>

      <div class="bg-mode-panel" data-mode="color">
        <label class="bg-field bg-field--color"><input type="color" name="colors.background" form="design-form"> Achtergrondkleur</label>
      </div>
    </fieldset>

    <div id="bg-preview" class="bg-preview" aria-label="Live preview">
      <div class="bg-preview-page">
        <img class="bg-preview-logo" src="<?= View::asset($settings['logo']) ?>" alt="">
        <div id="bg-preview-bookmarks" class="bg-preview-bookmarks"></div>
      </div>
    </div>

    <fieldset class="bg-mode-panel" data-mode="pattern">
      <legend id="bg-title"></legend>
      <div class="bg-pattern-head">
        <span id="bg-state" class="bg-state"></span>
        <button type="button" id="bg-reset" class="small">Standaardwaarden patroon</button>
      </div>
      <div id="bg-controls" class="bg-controls"></div>
      <p class="bg-credit">
        Patronen gebaseerd op <a href="https://www.svgbackgrounds.com/set/free-svg-backgrounds-and-patterns/" target="_blank" rel="noopener">Free SVG Backgrounds and Patterns</a>
        door <a href="https://www.youtube.com/@MattVisiwig" target="_blank" rel="noopener">Matt Visiwig</a>,
        <a href="https://www.svgbackgrounds.com/" target="_blank" rel="noopener">SVGBackgrounds.com</a>.
        De naamsvermelding staat ook in elk opgeslagen achtergrondbestand.
      </p>
    </fieldset>

    <fieldset>
      <legend>Kleuren</legend>
      <div class="color-grid">
        <label><input type="color" name="colors.text" form="design-form"> Tekst</label>
        <label><input type="color" name="colors.link" form="design-form"> Links</label>
        <label><input type="color" name="colors.link_hover" form="design-form"> Links bij hover</label>
        <label><input type="color" name="colors.accent" form="design-form"> Accent (focus, scrollbar)</label>
      </div>
      <p class="hint">Tekst en links worden iets gedempt weergegeven; bij hover in de volle kleur.</p>
      <button type="button" class="small" id="reset-colors">Standaardkleuren herstellen</button>
    </fieldset>

    <div class="form-actions">
      <button type="submit" class="primary" form="design-form">Opslaan</button>
      <a class="back" href="./" target="_blank">Bekijk site ↗</a>
    </div>
  </section>

  <!-- Instellingen -->
  <section id="tab-settings" class="tab-panel" role="tabpanel" hidden>
    <form id="design-form" class="design-form">
      <fieldset>
        <legend>Algemeen</legend>
        <label>Naam van de site
          <input type="text" name="name" maxlength="80" required>
        </label>
      </fieldset>

      <fieldset>
        <legend>Afbeeldingen</legend>
        <div class="asset-field" data-field="logo" data-slot="logo">
          <span class="asset-label">Logo</span>
          <div class="asset-preview"><img alt=""></div>
          <div class="asset-actions">
            <label class="button small">Uploaden…<input type="file" accept=".png,.jpg,.jpeg,.gif,.webp,.svg" hidden></label>
            <button type="button" class="small" data-pick-icon>Kies uit icons…</button>
            <button type="button" class="small" data-reset>Standaard</button>
          </div>
        </div>
        <div class="asset-field" data-field="favicon" data-slot="favicon">
          <span class="asset-label">Favicon</span>
          <div class="asset-preview"><img alt=""><span class="asset-empty">zelfde als logo</span></div>
          <div class="asset-actions">
            <label class="button small">Uploaden…<input type="file" accept=".png,.svg,.ico" hidden></label>
            <button type="button" class="small" data-pick-icon>Kies uit icons…</button>
            <button type="button" class="small" data-clear>Zelfde als logo</button>
          </div>
        </div>
      </fieldset>

      <fieldset>
        <legend>Indeling</legend>
        <div class="choice-group">
          <span class="choice-label">Kolommen</span>
          <label class="choice"><input type="radio" name="layout.columns" value="1"> 1</label>
          <label class="choice"><input type="radio" name="layout.columns" value="2"> 2</label>
          <label class="choice"><input type="radio" name="layout.columns" value="3"> 3</label>
        </div>
        <div class="choice-group">
          <span class="choice-label">Logo</span>
          <label class="choice"><input type="radio" name="layout.logo_position" value="left"> Links van de bookmarks</label>
          <label class="choice"><input type="radio" name="layout.logo_position" value="top"> Boven, gecentreerd</label>
        </div>
        <p class="hint">Welke categorie in welke kolom staat, bepaal je door te slepen in het tabblad Bookmarks. Op smalle schermen staan de kolommen onder elkaar en staat het logo bovenaan.</p>
      </fieldset>

    </form>

    <form id="password-form" class="design-form password-form" autocomplete="on">
      <fieldset>
        <legend>Wachtwoord</legend>
        <input type="text" name="username" value="home" autocomplete="username" hidden>
        <label>Huidig wachtwoord
          <input type="password" name="current" autocomplete="current-password" required>
        </label>
        <label>Nieuw wachtwoord
          <input type="password" name="new" autocomplete="new-password" minlength="<?= (int) $boot['limits']['password'] ?>" required>
        </label>
        <label>Herhaal nieuw wachtwoord
          <input type="password" name="repeat" autocomplete="new-password" minlength="<?= (int) $boot['limits']['password'] ?>" required>
        </label>
        <p class="hint">Minimaal <?= (int) $boot['limits']['password'] ?> tekens. Na het wijzigen worden alle andere apparaten uitgelogd; dit apparaat blijft ingelogd.</p>
        <button type="submit" class="primary">Wachtwoord wijzigen</button>
      </fieldset>
    </form>

    <!-- Hoort bij #design-form (via het form-attribuut), maar staat onder het wachtwoordblok -->
    <div class="design-form">
      <fieldset form="design-form">
        <legend>Extra opties</legend>
        <label class="checkbox"><input type="checkbox" name="logo_animation" form="design-form"> Logo springt en maakt geluid bij klikken</label>
      </fieldset>

      <div class="form-actions">
        <button type="submit" class="primary" form="design-form">Opslaan</button>
        <a class="back" href="./" target="_blank">Bekijk site ↗</a>
      </div>
    </div>
  </section>
</div>

<!-- Bookmark/categorie bewerken -->
<div id="node-overlay" class="overlay hidden">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <h2 id="modal-title"></h2>
    <form id="node-form">
      <label>Naam
        <input type="text" id="f-label" required>
      </label>
      <label id="f-parent-wrap">Plaats in
        <select id="f-parent"></select>
      </label>
      <label id="f-url-wrap">URL
        <input type="text" id="f-url" placeholder="https://…">
      </label>
      <label>Icoon
        <span class="icon-field">
          <img id="f-icon-preview" alt="">
          <input type="text" id="f-icon" placeholder="icons/naam.svg">
          <button type="button" id="f-icon-pick" class="small">Kies…</button>
        </span>
      </label>
      <label class="checkbox"><input type="checkbox" id="f-enabled" checked> Ingeschakeld</label>
      <div class="modal-actions">
        <button type="button" data-close>Annuleren</button>
        <button type="submit" class="primary">Opslaan</button>
      </div>
    </form>
  </div>
</div>

<!-- Icoon kiezen -->
<div id="picker-overlay" class="overlay hidden">
  <div class="modal icon-modal" role="dialog" aria-modal="true" aria-labelledby="picker-title">
    <h2 id="picker-title">Kies een icoon</h2>
    <div class="toolbar">
      <input type="search" id="picker-search" placeholder="Zoeken…" aria-label="Zoeken">
      <label class="button small">Uploaden…<input type="file" id="picker-upload" accept=".png,.jpg,.jpeg,.gif,.webp,.svg,.ico" hidden></label>
    </div>
    <div id="picker-grid" class="icon-grid"></div>
    <div class="modal-actions">
      <button type="button" data-close>Sluiten</button>
    </div>
  </div>
</div>

<script id="boot" type="application/json"><?= View::jsonForScript($boot) ?></script>
<script src="<?= View::asset('assets/js/backgrounds.js') ?>"></script>
<script src="<?= View::asset('assets/js/admin.js') ?>"></script>
</body>
</html>
