<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
    (function () {
      <?php
      $dm_enabled = get_theme_mod( 'kuh_darkmode_enabled', false ) ? 'true' : 'false';
      $dm_default = esc_js( get_theme_mod( 'kuh_darkmode_default_mode', 'auto' ) );
      ?>
      var enabled = <?php echo $dm_enabled; ?>;
      var defaultMode = '<?php echo $dm_default; ?>';
      if (!enabled) return;
      try {
        var m = localStorage.getItem('kuh-theme') || defaultMode;
        var isDark = m === 'dark' || (m === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        if (isDark) {
          document.documentElement.classList.add('dark');
          document.documentElement.style.colorScheme = 'dark';
        }
      } catch (e) {}
    })();
    </script>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    <?php wp_body_open(); ?>

    <div id="app"></div>

    <?php wp_footer(); ?>
</body>
</html>
