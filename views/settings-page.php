<?php
/**
 * Settings page view. $data comes from SettingsPage::render_page().
 */
?>
<div class="wrap">
    <h1><?php echo esc_html($data['title']); ?></h1>

    <?php settings_errors(); ?>

    <form method="post" action="options.php">
        <?php
            settings_fields($data['option_group']);
            do_settings_sections($data['page_slug']);
            submit_button();
        ?>
    </form>
</div>