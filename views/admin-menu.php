<?php
/**
 * Admin page: plain table of currently-unused attachments.
 * $data comes from AdminMenu::render_page() — see that method for shape.
 */

$results   = $data['results'];
$total     = $data['total'];
$page      = $data['page'];
$per_page  = $data['per_page'];
$last_page = (int) max(1, ceil($total / $per_page));
?>
<div class="wrap">
    <h1><?php echo esc_html($data['title']); ?></h1>

    <p><?php esc_html_e('Files that are unused in your site.', 'ghost-media-hunter'); ?></p>

    <p>
        <button type="button" class="button button-primary" id="gmh-scan-now">
            <?php esc_html_e('Scan now', 'ghost-media-hunter'); ?>
        </button>
        <span id="gmh-scan-status"></span>
    </p>

    <script>
    (function () {
        var button = document.getElementById('gmh-scan-now');
        var status = document.getElementById('gmh-scan-status');
        var scanningText = <?php echo wp_json_encode(__('Scanning…', 'ghost-media-hunter')); ?>;
        var failedText   = <?php echo wp_json_encode(__('Scan failed.', 'ghost-media-hunter')); ?>;

        button.addEventListener('click', function () {
            button.disabled = true;
            status.textContent = scanningText;

            var data = new FormData();
            data.append('action', <?php echo wp_json_encode(\GhostMediaHunter\Services\ScanTrigger::ACTION); ?>);
            data.append('_wpnonce', <?php echo wp_json_encode(wp_create_nonce(\GhostMediaHunter\Services\ScanTrigger::ACTION)); ?>);

            fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {
                method: 'POST',
                credentials: 'same-origin',
                body: data,
            })
                .then(function (response) { return response.json(); })
                .then(function (result) {
                    if (result && result.success) {
                        window.location.reload();
                        return;
                    }
                    button.disabled = false;
                    status.textContent = failedText;
                })
                .catch(function () {
                    button.disabled = false;
                    status.textContent = failedText;
                });
        });
    })();
    </script>

    <?php if (empty($results)) : ?>
        <p><?php esc_html_e('No unused media found yet.', 'ghost-media-hunter'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('File', 'ghost-media-hunter'); ?></th>
                    <th><?php esc_html_e('Size', 'ghost-media-hunter'); ?></th>
                    <th><?php esc_html_e('Last checked', 'ghost-media-hunter'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row) : ?>
                    <?php
                        $edit_link = get_edit_post_link((int) $row->attachment_id);
                        $title     = get_the_title((int) $row->attachment_id);
                        $title     = $title !== '' ? $title : sprintf('#%d', $row->attachment_id);
                    ?>
                    <tr>
                        <td>
                            <?php if ($edit_link) : ?>
                                <a href="<?php echo esc_url($edit_link); ?>"><?php echo esc_html($title); ?></a>
                            <?php else : ?>
                                <?php echo esc_html($title); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(size_format((int) $row->file_size)); ?></td>
                        <td><?php echo esc_html($row->last_checked); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($last_page > 1) : ?>
            <p class="tablenav-pages">
                <?php if ($page > 1) : ?>
                    <a class="button" href="<?php echo esc_url(add_query_arg('paged', $page - 1)); ?>">
                        <?php esc_html_e('Previous', 'ghost-media-hunter'); ?>
                    </a>
                <?php endif; ?>

                <span>
                    <?php printf(esc_html__('Page %1$d of %2$d', 'ghost-media-hunter'), $page, $last_page); ?>
                </span>

                <?php if ($page < $last_page) : ?>
                    <a class="button" href="<?php echo esc_url(add_query_arg('paged', $page + 1)); ?>">
                        <?php esc_html_e('Next', 'ghost-media-hunter'); ?>
                    </a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>