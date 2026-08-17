<?php
/**
 * Admin page: plain table of currently-unused attachments.
 * $data comes from AdminMenu::render_page() — see that method for shape.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

/**
 * Data passed to this view from AdminMenu::render_page().
 *
 * @var array{
 *     title: string,
 *     view: string,
 *     page: int,
 *     per_page: int,
 *     unused_total: int,
 *     kept_total: int,
 *     results: array<int, object>,
 *     total: int
 * } $data Passed in from AdminMenu::render_page().
 */

$gmh_results   = $data['results'];
$gmh_total     = $data['total'];
$gmh_page      = $data['page'];
$gmh_per_page  = $data['per_page'];
$gmh_view      = $data['view'];
$gmh_last_page = (int) max( 1, ceil( $gmh_total / $gmh_per_page ) );
?>
<div class="wrap">
	<h1><?php echo esc_html( $data['title'] ); ?></h1>

	<h2 class="nav-tab-wrapper">
		<a href="<?php echo esc_url( remove_query_arg( array( 'view', 'paged' ) ) ); ?>" class="nav-tab <?php echo 'unused' === $gmh_view ? 'nav-tab-active' : ''; ?>">
			<?php
			/* translators: %d: Number of media items that are unused in the site. */
			printf( esc_html__( 'Unused (%d)', 'ghost-media-hunter' ), (int) $data['unused_total'] );
			?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( array( 'view' => 'kept' ), remove_query_arg( 'paged' ) ) ); ?>" class="nav-tab <?php echo 'kept' === $gmh_view ? 'nav-tab-active' : ''; ?>">
			<?php
			/* translators: %d: Number of media items that are being kept. */
			printf( esc_html__( 'Kept (%d)', 'ghost-media-hunter' ), (int) $data['kept_total'] );
			?>
		</a>
	</h2>

	<p>
		<?php
		if ( 'kept' === $gmh_view ) :
			?>
			<?php esc_html_e( 'Files you\'ve marked "Keep" — excluded from the Unused list even if the scanner still can\'t find a reference to them.', 'ghost-media-hunter' ); ?>
			<?php
		else :
			?>
			<?php esc_html_e( 'Files that are unused in your site.', 'ghost-media-hunter' ); ?>
			<?php
		endif;
		?>
	</p>

	<p>
		<button type="button" class="button button-primary" id="gmh-scan-now">
			<?php esc_html_e( 'Scan now', 'ghost-media-hunter' ); ?>
		</button>
		<span id="gmh-scan-status"></span>
	</p>

	<script>
	(function () {
		var button = document.getElementById('gmh-scan-now');
		var status = document.getElementById('gmh-scan-status');
		var scanningText = <?php echo wp_json_encode( __( 'Scanning…', 'ghost-media-hunter' ) ); ?>;
		var failedText   = <?php echo wp_json_encode( __( 'Scan failed.', 'ghost-media-hunter' ) ); ?>;

		button.addEventListener('click', function () {
			button.disabled = true;
			status.textContent = scanningText;

			var data = new FormData();
			data.append('action', <?php echo wp_json_encode( \GhostMediaHunter\Services\ScanTrigger::ACTION ); ?>);
			data.append('_wpnonce', <?php echo wp_json_encode( wp_create_nonce( \GhostMediaHunter\Services\ScanTrigger::ACTION ) ); ?>);

			fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
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
					status.textContent = (result && result.data && result.data.message) ? result.data.message : failedText;
				})
				.catch(function () {
					button.disabled = false;
					status.textContent = failedText;
				});
		});

		// Row actions (Keep / Delete) — real event delegation on .wrap,
		// since the table (and its buttons) is rendered further down the
		// page, AFTER this script tag runs. Binding listeners directly to
		// .gmh-row-action elements here would find zero of them (they
		// don't exist in the DOM yet at this point) — delegating to an
		// ancestor that already exists, and checking event.target on
		// click, works regardless of DOM order.
		var wrap = document.querySelector('.wrap');

		wrap.addEventListener('click', function (event) {
			var btn = event.target.closest('.gmh-row-action');
			if (!btn) {
				return;
			}

			var confirmMsg = btn.getAttribute('data-gmh-confirm');
			if (confirmMsg && !window.confirm(confirmMsg)) {
				return;
			}

			var row = btn.closest('tr');
			var otherButtons = row ? row.querySelectorAll('.gmh-row-action') : [btn];
			otherButtons.forEach(function (b) { b.disabled = true; });

			var data = new FormData();
			data.append('action', btn.getAttribute('data-gmh-action'));
			data.append('_wpnonce', btn.getAttribute('data-gmh-nonce'));
			data.append('attachment_id', btn.getAttribute('data-attachment-id'));

			fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
				method: 'POST',
				credentials: 'same-origin',
				body: data,
			})
				.then(function (response) { return response.json(); })
				.then(function (result) {
					if (result && result.success) {
						if (row) {
							row.remove();
						}
						return;
					}
					otherButtons.forEach(function (b) { b.disabled = false; });
					window.alert((result && result.data && result.data.message) ? result.data.message : failedText);
				})
				.catch(function () {
					otherButtons.forEach(function (b) { b.disabled = false; });
					window.alert(failedText);
				});
		});
	})();
	</script>

	<?php
	if ( empty( $gmh_results ) ) :
		?>
		<p>
			<?php
			if ( 'kept' === $gmh_view ) :
				?>
				<?php esc_html_e( 'Nothing kept yet.', 'ghost-media-hunter' ); ?>
				<?php
			else :
				?>
				<?php esc_html_e( 'No unused media found yet.', 'ghost-media-hunter' ); ?>
				<?php
			endif;
			?>
		</p>
		<?php
	else :
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'File', 'ghost-media-hunter' ); ?></th>
					<th><?php esc_html_e( 'Size', 'ghost-media-hunter' ); ?></th>
					<th><?php esc_html_e( 'Last checked', 'ghost-media-hunter' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'ghost-media-hunter' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $gmh_results as $row ) :
					?>
					<?php
						$edit_link = get_edit_post_link( (int) $row->attachment_id );
						$gmh_title = get_the_title( (int) $row->attachment_id );
						$gmh_title = '' !== $gmh_title ? $gmh_title : sprintf( '#%d', $row->attachment_id );
					?>
					<tr>
						<td>
							<?php
							if ( $edit_link ) :
								?>
								<a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $gmh_title ); ?></a>
								<?php
							else :
								echo esc_html( $gmh_title );
							endif;
							?>
						</td>
						<td><?php echo esc_html( size_format( (int) $row->file_size ) ); ?></td>
						<td><?php echo esc_html( $row->last_checked ); ?></td>
						<td>
							<?php
							if ( 'kept' === $gmh_view ) :
								?>
								<button
									type="button"
									class="button gmh-row-action"
									data-gmh-action="<?php echo esc_attr( \GhostMediaHunter\Services\ResultActions::ACTION_RESTORE ); ?>"
									data-gmh-nonce="<?php echo esc_attr( wp_create_nonce( \GhostMediaHunter\Services\ResultActions::ACTION_RESTORE ) ); ?>"
									data-attachment-id="<?php echo esc_attr( $row->attachment_id ); ?>"
								><?php esc_html_e( 'Restore', 'ghost-media-hunter' ); ?></button>
								<?php
							else :
								?>
								<button
									type="button"
									class="button gmh-row-action"
									data-gmh-action="<?php echo esc_attr( \GhostMediaHunter\Services\ResultActions::ACTION_KEEP ); ?>"
									data-gmh-nonce="<?php echo esc_attr( wp_create_nonce( \GhostMediaHunter\Services\ResultActions::ACTION_KEEP ) ); ?>"
									data-attachment-id="<?php echo esc_attr( $row->attachment_id ); ?>"
								><?php esc_html_e( 'Keep', 'ghost-media-hunter' ); ?></button>
								<?php
							endif;
							?>
							<button
								type="button"
								class="button gmh-row-action"
								data-gmh-action="<?php echo esc_attr( \GhostMediaHunter\Services\ResultActions::ACTION_DELETE ); ?>"
								data-gmh-nonce="<?php echo esc_attr( wp_create_nonce( \GhostMediaHunter\Services\ResultActions::ACTION_DELETE ) ); ?>"
								data-attachment-id="<?php echo esc_attr( $row->attachment_id ); ?>"
								data-gmh-confirm="<?php echo esc_attr__( 'Delete this file? Unless your site has MEDIA_TRASH enabled, this is PERMANENT — it will not go to the trash.', 'ghost-media-hunter' ); ?>"
							><?php esc_html_e( 'Delete', 'ghost-media-hunter' ); ?></button>
						</td>
					</tr>
					<?php
				endforeach;
				?>
			</tbody>
		</table>

		<?php
		if ( $gmh_last_page > 1 ) :
			?>
			<p class="tablenav-pages">
				<?php
				if ( $gmh_page > 1 ) :
					?>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $gmh_page - 1 ) ); ?>">
						<?php esc_html_e( 'Previous', 'ghost-media-hunter' ); ?>
					</a>
					<?php
				endif;
				?>

				<span>
					<?php
					printf(
						/* translators: %1$d: Current page number. %2$d: Total number of pages. */
						esc_html__( 'Page %1$d of %2$d', 'ghost-media-hunter' ),
						(int) $gmh_page,
						(int) $gmh_last_page
					);
					?>
				</span>

				<?php
				if ( $gmh_page < $gmh_last_page ) :
					?>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $gmh_page + 1 ) ); ?>">
						<?php esc_html_e( 'Next', 'ghost-media-hunter' ); ?>
					</a>
					<?php
				endif;
				?>
			</p>
			<?php
		endif;
		?>
		<?php
	endif;
	?>
</div>
