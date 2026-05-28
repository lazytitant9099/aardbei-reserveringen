<?php
/**
 * Reservations view.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$status    = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$args = array( 'limit' => 500 );
if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
	$args['date_from'] = $date_from;
}
if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
	$args['date_to'] = $date_to;
}
if ( in_array( $status, array( 'confirmed', 'cancelled' ), true ) ) {
	$args['status'] = $status;
}

$reservations_helper = new Aardbei_Reserveringen_Reservations();
$reservations        = $reservations_helper->get_reservations( $args );

$slots_helper = new Aardbei_Reserveringen_Slots();
$today        = date_i18n( 'Y-m-d', current_time( 'timestamp' ) );
$end_date     = date_i18n( 'Y-m-d', current_time( 'timestamp' ) + ( 90 * DAY_IN_SECONDS ) );
$open_slots   = $slots_helper->get_slots( $today, $end_date, true );
?>
<div class="wrap aardbei-admin-wrap">
	<div class="aardbei-page-header">
		<div>
			<h1><?php echo esc_html__( 'Reserveringen', 'aardbei-reserveringen' ); ?></h1>
			<p class="aardbei-page-subtitle"><?php echo esc_html( sprintf( __( '%d gevonden', 'aardbei-reserveringen' ), count( $reservations ) ) ); ?></p>
		</div>
	</div>

	<!-- Filter -->
	<div class="aardbei-form-card">
		<form method="get" class="aardbei-filter-form">
			<input type="hidden" name="page" value="aardbei-reserveringen-reservations">
			<label>
				<?php echo esc_html__( 'Datum vanaf', 'aardbei-reserveringen' ); ?>
				<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
			</label>
			<label>
				<?php echo esc_html__( 'Datum tot', 'aardbei-reserveringen' ); ?>
				<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
			</label>
			<label>
				<?php echo esc_html__( 'Status', 'aardbei-reserveringen' ); ?>
				<select name="status">
					<option value=""><?php echo esc_html__( 'Alle statussen', 'aardbei-reserveringen' ); ?></option>
					<option value="confirmed" <?php selected( $status, 'confirmed' ); ?>><?php echo esc_html__( 'Bevestigd', 'aardbei-reserveringen' ); ?></option>
					<option value="cancelled" <?php selected( $status, 'cancelled' ); ?>><?php echo esc_html__( 'Geannuleerd', 'aardbei-reserveringen' ); ?></option>
				</select>
			</label>
			<button type="submit" class="aardbei-btn aardbei-btn--primary">
				<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
				<?php echo esc_html__( 'Filteren', 'aardbei-reserveringen' ); ?>
			</button>
			<?php if ( $date_from || $date_to || $status ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aardbei-reserveringen-reservations' ) ); ?>" class="aardbei-btn aardbei-btn--outline">
					<?php echo esc_html__( 'Wis filter', 'aardbei-reserveringen' ); ?>
				</a>
			<?php endif; ?>
		</form>
	</div>

	<!-- Handmatige reservering toevoegen -->
	<details class="aardbei-form-card" style="cursor:pointer;">
		<summary style="font-size:14px;font-weight:700;color:#0f172a;list-style:none;display:flex;align-items:center;gap:8px;">
			<svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
			<?php echo esc_html__( 'Handmatige reservering toevoegen', 'aardbei-reserveringen' ); ?>
		</summary>
		<div style="margin-top:16px;">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aardbei-inline-form" style="flex-wrap:wrap;">
				<input type="hidden" name="action" value="aardbei_add_reservation">
				<?php wp_nonce_field( 'aardbei_add_reservation' ); ?>
				<label>
					<?php echo esc_html__( 'Tijdslot', 'aardbei-reserveringen' ); ?>
					<select name="slot_id" required style="min-width:200px;">
						<option value=""><?php echo esc_html__( 'Kies tijdslot…', 'aardbei-reserveringen' ); ?></option>
						<?php foreach ( $open_slots as $slot ) : ?>
							<option value="<?php echo esc_attr( $slot['id'] ); ?>">
								<?php
								printf(
									'%s – %s – %s (%s)',
									esc_html( date_i18n( get_option( 'date_format' ), strtotime( $slot['date'] ) ) ),
									esc_html( substr( $slot['start_time'], 0, 5 ) ),
									esc_html( substr( $slot['end_time'], 0, 5 ) ),
									esc_html( sprintf(
										/* translators: %d: remaining places. */
										_n( 'nog %d plek', 'nog %d plekken', $slot['remaining'], 'aardbei-reserveringen' ),
										$slot['remaining']
									) )
								);
								?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<?php echo esc_html__( 'Naam', 'aardbei-reserveringen' ); ?>
					<input type="text" name="name" required placeholder="Volledige naam">
				</label>
				<label>
					<?php echo esc_html__( 'E-mail', 'aardbei-reserveringen' ); ?>
					<input type="email" name="email" required placeholder="email@voorbeeld.nl">
				</label>
				<label>
					<?php echo esc_html__( 'Telefoon', 'aardbei-reserveringen' ); ?>
					<input type="text" name="phone" required placeholder="06-12345678">
				</label>
				<label>
					<?php echo esc_html__( 'Personen', 'aardbei-reserveringen' ); ?>
					<input type="number" name="persons" min="1" value="2" required style="width:72px;">
				</label>
				<label>
					<?php echo esc_html__( 'Opmerking', 'aardbei-reserveringen' ); ?>
					<input type="text" name="note" placeholder="Optioneel">
				</label>
				<button type="submit" class="aardbei-btn aardbei-btn--success">
					<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
					<?php echo esc_html__( 'Reservering aanmaken', 'aardbei-reserveringen' ); ?>
				</button>
			</form>
		</div>
	</details>

	<!-- Toolbar: zoeken + bulk + export -->
	<div class="aardbei-toolbar">
		<div class="aardbei-toolbar-left">
			<div class="aardbei-search-input-wrap">
				<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
				<input type="text" id="aardbei-search" class="aardbei-search-input" placeholder="<?php echo esc_attr__( 'Zoek op naam, e-mail of telefoon…', 'aardbei-reserveringen' ); ?>">
			</div>
			<span class="aardbei-result-count" id="aardbei-result-count"></span>
		</div>
		<div class="aardbei-toolbar-right">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
				<input type="hidden" name="action" value="aardbei_export_reservations">
				<input type="hidden" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
				<input type="hidden" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
				<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
				<?php wp_nonce_field( 'aardbei_export_reservations' ); ?>
				<button type="submit" class="aardbei-btn aardbei-btn--outline">
					<svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
					<?php echo esc_html__( 'Exporteren als CSV', 'aardbei-reserveringen' ); ?>
				</button>
			</form>
		</div>
	</div>

	<!-- Bulk acties bar (verborgen totdat checkboxes geselecteerd zijn) -->
	<div class="aardbei-bulk-bar" id="aardbei-bulk-bar" hidden>
		<span class="aardbei-bulk-count" id="aardbei-bulk-count"></span>
		<button type="button" class="aardbei-btn aardbei-btn--danger aardbei-btn--sm" id="aardbei-bulk-cancel"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'aardbei_admin_nonce' ) ); ?>">
			<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
			<?php echo esc_html__( 'Annuleer geselecteerde', 'aardbei-reserveringen' ); ?>
		</button>
		<button type="button" class="aardbei-btn aardbei-btn--outline aardbei-btn--sm" id="aardbei-bulk-deselect">
			<?php echo esc_html__( 'Deselecteer', 'aardbei-reserveringen' ); ?>
		</button>
	</div>

	<!-- Tabel -->
	<div class="aardbei-table-wrap">
		<table class="aardbei-table" id="aardbei-reservations-table">
			<thead>
				<tr>
					<th class="aardbei-col-check">
						<input type="checkbox" id="aardbei-select-all" title="<?php echo esc_attr__( 'Alles selecteren', 'aardbei-reserveringen' ); ?>">
					</th>
					<th><?php echo esc_html__( 'Datum & Tijd', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Contact', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Personen', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Aangemeld op', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Actie', 'aardbei-reserveringen' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $reservations ) ) : ?>
					<tr><td colspan="7" class="aardbei-table-empty"><?php echo esc_html__( 'Geen reserveringen gevonden.', 'aardbei-reserveringen' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $reservations as $reservation ) : ?>
						<?php
						$search_data = strtolower( $reservation['name'] . ' ' . $reservation['email'] . ' ' . $reservation['phone'] );
						$cancel_url  = esc_url( add_query_arg( array(
							'aardbei_cancel' => 1,
							'token'          => $reservation['cancel_token'],
						), home_url() ) );
						?>
						<tr data-search="<?php echo esc_attr( $search_data ); ?>"
							data-id="<?php echo esc_attr( $reservation['id'] ); ?>"
							class="aardbei-expandable-row <?php echo 'confirmed' === $reservation['status'] ? 'is-confirmed' : 'is-cancelled'; ?>">
							<td class="aardbei-col-check" data-no-expand>
								<?php if ( 'confirmed' === $reservation['status'] ) : ?>
									<input type="checkbox" class="aardbei-row-check" value="<?php echo esc_attr( $reservation['id'] ); ?>">
								<?php endif; ?>
							</td>
							<td>
								<strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $reservation['date'] ) ) ); ?></strong>
								<small class="aardbei-muted-text"><?php echo esc_html( substr( $reservation['start_time'], 0, 5 ) . ' – ' . substr( $reservation['end_time'], 0, 5 ) ); ?></small>
							</td>
							<td class="col-name">
								<strong><?php echo esc_html( $reservation['name'] ); ?></strong>
								<small>
									<a href="mailto:<?php echo esc_attr( $reservation['email'] ); ?>"><?php echo esc_html( $reservation['email'] ); ?></a>
								</small>
							</td>
							<td class="aardbei-num"><?php echo esc_html( number_format_i18n( $reservation['persons'] ) ); ?></td>
							<td>
								<span class="aardbei-status aardbei-status-<?php echo esc_attr( $reservation['status'] ); ?>">
									<?php echo esc_html( 'confirmed' === $reservation['status'] ? __( 'Bevestigd', 'aardbei-reserveringen' ) : __( 'Geannuleerd', 'aardbei-reserveringen' ) ); ?>
								</span>
							</td>
							<td class="aardbei-muted-text" style="font-size:12px;"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $reservation['created_at'] ) ) ); ?></td>
							<td data-no-expand>
								<?php if ( 'confirmed' === $reservation['status'] ) : ?>
									<button type="button"
										class="aardbei-btn aardbei-btn--danger aardbei-btn--sm aardbei-ajax-cancel"
										data-id="<?php echo esc_attr( $reservation['id'] ); ?>"
										data-nonce="<?php echo esc_attr( wp_create_nonce( 'aardbei_admin_nonce' ) ); ?>"
									>
										<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
										<?php echo esc_html__( 'Annuleren', 'aardbei-reserveringen' ); ?>
									</button>
								<?php else : ?>
									<span class="aardbei-muted-text" style="font-size:12px;">—</span>
								<?php endif; ?>
							</td>
						</tr>
						<!-- Detail rij (verborgen) -->
						<tr class="aardbei-detail-row" hidden>
							<td colspan="7">
								<div class="aardbei-detail-content">
									<div class="aardbei-detail-grid">
										<div class="aardbei-detail-item">
											<span class="aardbei-detail-label"><?php echo esc_html__( 'Naam', 'aardbei-reserveringen' ); ?></span>
											<span class="aardbei-detail-value"><?php echo esc_html( $reservation['name'] ); ?></span>
										</div>
										<div class="aardbei-detail-item">
											<span class="aardbei-detail-label"><?php echo esc_html__( 'E-mail', 'aardbei-reserveringen' ); ?></span>
											<span class="aardbei-detail-value">
												<a href="mailto:<?php echo esc_attr( $reservation['email'] ); ?>"><?php echo esc_html( $reservation['email'] ); ?></a>
											</span>
										</div>
										<div class="aardbei-detail-item">
											<span class="aardbei-detail-label"><?php echo esc_html__( 'Telefoon', 'aardbei-reserveringen' ); ?></span>
											<span class="aardbei-detail-value">
												<?php if ( $reservation['phone'] ) : ?>
													<a href="tel:<?php echo esc_attr( $reservation['phone'] ); ?>"><?php echo esc_html( $reservation['phone'] ); ?></a>
												<?php else : ?>
													—
												<?php endif; ?>
											</span>
										</div>
										<div class="aardbei-detail-item">
											<span class="aardbei-detail-label"><?php echo esc_html__( 'Personen', 'aardbei-reserveringen' ); ?></span>
											<span class="aardbei-detail-value"><?php echo esc_html( $reservation['persons'] ); ?></span>
										</div>
										<?php if ( $reservation['note'] ) : ?>
										<div class="aardbei-detail-item aardbei-detail-item--full">
											<span class="aardbei-detail-label"><?php echo esc_html__( 'Opmerking', 'aardbei-reserveringen' ); ?></span>
											<span class="aardbei-detail-value"><?php echo esc_html( $reservation['note'] ); ?></span>
										</div>
										<?php endif; ?>
										<?php if ( 'confirmed' === $reservation['status'] ) : ?>
										<div class="aardbei-detail-item">
											<span class="aardbei-detail-label"><?php echo esc_html__( 'Annuleerlink', 'aardbei-reserveringen' ); ?></span>
											<span class="aardbei-detail-value">
												<a href="<?php echo esc_url( $cancel_url ); ?>" class="aardbei-muted-text" style="font-size:11px;word-break:break-all;">
													<?php echo esc_html( $cancel_url ); ?>
												</a>
											</span>
										</div>
										<?php endif; ?>
									</div>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
