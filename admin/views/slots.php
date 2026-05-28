<?php
/**
 * Slots view.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$slots_helper = new Aardbei_Reserveringen_Slots();
$start_date   = date_i18n( 'Y-m-d', current_time( 'timestamp' ) );
$end_date     = date_i18n( 'Y-m-d', current_time( 'timestamp' ) + ( 90 * DAY_IN_SECONDS ) );
$slots        = $slots_helper->get_slots( $start_date, $end_date, false );

$two_weeks_end   = date_i18n( 'Y-m-d', current_time( 'timestamp' ) + ( 14 * DAY_IN_SECONDS ) );
$upcoming_open   = $slots_helper->get_slots( $start_date, $two_weeks_end, true );
$show_warning    = count( $upcoming_open ) < 3;
?>
<div class="wrap aardbei-admin-wrap">
	<div class="aardbei-page-header">
		<div>
			<h1><?php echo esc_html__( 'Tijdsloten', 'aardbei-reserveringen' ); ?></h1>
			<p class="aardbei-page-subtitle">
				<?php echo esc_html( sprintf( __( '%d sloten de komende 90 dagen', 'aardbei-reserveringen' ), count( $slots ) ) ); ?>
			</p>
		</div>
	</div>

	<?php if ( $show_warning ) : ?>
	<div class="aardbei-alert aardbei-alert--warning">
		<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
		<div>
			<strong><?php echo esc_html__( 'Weinig open tijdsloten', 'aardbei-reserveringen' ); ?></strong>
			<?php echo esc_html__( 'Er zijn minder dan 3 open tijdsloten de komende 2 weken. Genereer nieuwe slots vanuit het weekschema.', 'aardbei-reserveringen' ); ?>
		</div>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=aardbei-reserveringen-week-template' ) ); ?>" class="aardbei-btn aardbei-btn--outline aardbei-btn--sm">
			<?php echo esc_html__( 'Naar weekschema', 'aardbei-reserveringen' ); ?>
		</a>
	</div>
	<?php endif; ?>

	<div class="aardbei-form-card">
		<h3><?php echo esc_html__( 'Tijdslot handmatig toevoegen', 'aardbei-reserveringen' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aardbei-inline-form">
			<input type="hidden" name="action" value="aardbei_add_slot">
			<?php wp_nonce_field( 'aardbei_add_slot' ); ?>
			<label>
				<?php echo esc_html__( 'Datum', 'aardbei-reserveringen' ); ?>
				<input type="date" name="date" required>
			</label>
			<label>
				<?php echo esc_html__( 'Starttijd', 'aardbei-reserveringen' ); ?>
				<input type="time" name="start_time" required>
			</label>
			<label>
				<?php echo esc_html__( 'Eindtijd', 'aardbei-reserveringen' ); ?>
				<input type="time" name="end_time" required>
			</label>
			<label>
				<?php echo esc_html__( 'Capaciteit', 'aardbei-reserveringen' ); ?>
				<input type="number" name="capacity" min="1" required style="width:80px;">
			</label>
			<label>
				<?php echo esc_html__( 'Status', 'aardbei-reserveringen' ); ?>
				<select name="status">
					<option value="open"><?php echo esc_html__( 'Open', 'aardbei-reserveringen' ); ?></option>
					<option value="closed"><?php echo esc_html__( 'Gesloten', 'aardbei-reserveringen' ); ?></option>
				</select>
			</label>
			<button type="submit" class="aardbei-btn aardbei-btn--primary">
				<svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
				<?php echo esc_html__( 'Toevoegen', 'aardbei-reserveringen' ); ?>
			</button>
		</form>
	</div>

	<h2 class="aardbei-section-title">
		<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
		<?php echo esc_html__( 'Komende tijdsloten (90 dagen)', 'aardbei-reserveringen' ); ?>
	</h2>
	<div class="aardbei-table-wrap">
		<table class="aardbei-table" id="aardbei-slots-table">
			<thead>
				<tr>
					<th style="width:32px;">
						<input type="checkbox" id="aardbei-slots-select-all" title="<?php echo esc_attr__( 'Alles selecteren', 'aardbei-reserveringen' ); ?>">
					</th>
					<th><?php echo esc_html__( 'Datum', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Tijd', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Bezetting', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Capaciteit', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Acties', 'aardbei-reserveringen' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $slots ) ) : ?>
					<tr><td colspan="7" class="aardbei-table-empty"><?php echo esc_html__( 'Geen komende tijdsloten gevonden.', 'aardbei-reserveringen' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $slots as $slot ) :
						$capacity     = max( 1, (int) $slot['capacity'] );
						$booked       = (int) $slot['booked_persons'];
						$remaining    = (int) $slot['remaining'];
						$fill_pct     = min( 100, round( $booked / $capacity * 100 ) );
						$fill_class   = $fill_pct >= 90 ? 'high' : ( $fill_pct >= 60 ? 'medium' : '' );
						$is_closed    = 'closed' === $slot['status'] || (int) $slot['manual_closed'];
						$can_delete   = $booked === 0;
					?>
						<tr data-slot-id="<?php echo esc_attr( $slot['id'] ); ?>">
							<td>
								<input type="checkbox" class="aardbei-slot-check" value="<?php echo esc_attr( $slot['id'] ); ?>"<?php echo $can_delete ? '' : ' disabled title="' . esc_attr__( 'Heeft actieve reserveringen', 'aardbei-reserveringen' ) . '"'; ?>>
							</td>
							<td>
								<strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $slot['date'] ) ) ); ?></strong>
							</td>
							<td style="white-space:nowrap;">
								<?php echo esc_html( substr( $slot['start_time'], 0, 5 ) . ' – ' . substr( $slot['end_time'], 0, 5 ) ); ?>
							</td>
							<td>
								<div class="aardbei-capacity-wrap">
									<div class="aardbei-capacity-bar">
										<div class="aardbei-capacity-fill aardbei-capacity-fill--<?php echo esc_attr( $fill_class ); ?>" style="width:<?php echo esc_attr( $fill_pct ); ?>%"></div>
									</div>
									<span class="aardbei-capacity-label"><?php echo esc_html( $booked . ' / ' . $capacity ); ?></span>
									<?php if ( ! $is_closed && $remaining > 0 ) : ?>
										<span class="aardbei-capacity-remaining">
											<?php echo esc_html( sprintf(
												/* translators: %d: remaining places. */
												_n( '%d vrij', '%d vrij', $remaining, 'aardbei-reserveringen' ),
												$remaining
											) ); ?>
										</span>
									<?php endif; ?>
								</div>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aardbei-mini-form">
									<input type="hidden" name="action" value="aardbei_update_slot_capacity">
									<input type="hidden" name="slot_id" value="<?php echo esc_attr( $slot['id'] ); ?>">
									<?php wp_nonce_field( 'aardbei_update_slot_capacity_' . absint( $slot['id'] ) ); ?>
									<input type="number" name="capacity" min="1" value="<?php echo esc_attr( $slot['capacity'] ); ?>">
									<button type="submit" class="aardbei-btn aardbei-btn--outline aardbei-btn--sm">
										<?php echo esc_html__( 'Aanpassen', 'aardbei-reserveringen' ); ?>
									</button>
								</form>
							</td>
							<td>
								<?php
								if ( $is_closed ) {
									$status_key   = 'closed';
									$status_label = __( 'Gesloten', 'aardbei-reserveringen' );
								} elseif ( $remaining <= 0 ) {
									$status_key   = 'full';
									$status_label = __( 'Vol', 'aardbei-reserveringen' );
								} else {
									$status_key   = 'open';
									$status_label = __( 'Open', 'aardbei-reserveringen' );
								}
								?>
								<span class="aardbei-status aardbei-status-<?php echo esc_attr( $status_key ); ?>"><?php echo esc_html( $status_label ); ?></span>
							</td>
							<td style="white-space:nowrap;">
								<div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center;">
									<?php if ( $is_closed ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<input type="hidden" name="action" value="aardbei_open_slot">
											<input type="hidden" name="slot_id" value="<?php echo esc_attr( $slot['id'] ); ?>">
											<?php wp_nonce_field( 'aardbei_open_slot_' . absint( $slot['id'] ) ); ?>
											<button type="submit" class="aardbei-btn aardbei-btn--success aardbei-btn--sm">
												<svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
												<?php echo esc_html__( 'Openen', 'aardbei-reserveringen' ); ?>
											</button>
										</form>
									<?php else : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<input type="hidden" name="action" value="aardbei_close_slot">
											<input type="hidden" name="slot_id" value="<?php echo esc_attr( $slot['id'] ); ?>">
											<?php wp_nonce_field( 'aardbei_close_slot_' . absint( $slot['id'] ) ); ?>
											<button type="submit" class="aardbei-btn aardbei-btn--danger aardbei-btn--sm">
												<svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
												<?php echo esc_html__( 'Sluiten', 'aardbei-reserveringen' ); ?>
											</button>
										</form>
									<?php endif; ?>

									<?php if ( $can_delete ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<input type="hidden" name="action" value="aardbei_delete_slot">
											<input type="hidden" name="slot_id" value="<?php echo esc_attr( $slot['id'] ); ?>">
											<?php wp_nonce_field( 'aardbei_delete_slot_' . absint( $slot['id'] ) ); ?>
											<button type="submit" class="aardbei-btn aardbei-btn--outline aardbei-btn--sm aardbei-btn--delete-slot">
												<svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
												<?php echo esc_html__( 'Verwijderen', 'aardbei-reserveringen' ); ?>
											</button>
										</form>
									<?php else : ?>
										<button type="button" class="aardbei-btn aardbei-btn--outline aardbei-btn--sm" disabled title="<?php echo esc_attr__( 'Heeft actieve reserveringen', 'aardbei-reserveringen' ); ?>">
											<svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
											<?php echo esc_html__( 'Verwijderen', 'aardbei-reserveringen' ); ?>
										</button>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<div id="aardbei-slots-bulk-bar" class="aardbei-bulk-bar" hidden>
		<span id="aardbei-slots-bulk-count"></span>
		<button type="button" id="aardbei-slots-bulk-delete" class="aardbei-btn aardbei-btn--danger aardbei-btn--sm">
			<svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
			<?php echo esc_html__( 'Verwijder geselecteerde', 'aardbei-reserveringen' ); ?>
		</button>
		<button type="button" id="aardbei-slots-bulk-deselect" class="aardbei-btn aardbei-btn--outline aardbei-btn--sm">
			<?php echo esc_html__( 'Deselecteer alles', 'aardbei-reserveringen' ); ?>
		</button>
	</div>
</div>
