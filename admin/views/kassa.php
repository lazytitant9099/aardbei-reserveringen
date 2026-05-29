<?php
/**
 * Kassa view — klanten aanmelden bij binnenkomst.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$date = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
	$date = date_i18n( 'Y-m-d', current_time( 'timestamp' ) );
}
$today = date_i18n( 'Y-m-d', current_time( 'timestamp' ) );

$reservations_helper = new Aardbei_Reserveringen_Reservations();
$reservations        = $reservations_helper->get_reservations(
	array(
		'date_from' => $date,
		'date_to'   => $date,
		'limit'     => 500,
	)
);

// Groepeer op slot, gesorteerd op starttijd.
$slots_grouped = array();
foreach ( $reservations as $res ) {
	if ( 'cancelled' === $res['status'] ) {
		continue;
	}
	$slot_id = $res['slot_id'];
	if ( ! isset( $slots_grouped[ $slot_id ] ) ) {
		$slots_grouped[ $slot_id ] = array(
			'start_time'   => $res['start_time'],
			'end_time'     => $res['end_time'],
			'reservations' => array(),
			'checked_in'   => 0,
			'total'        => 0,
		);
	}
	$slots_grouped[ $slot_id ]['reservations'][] = $res;
	$slots_grouped[ $slot_id ]['total']++;
	if ( ! empty( $res['checked_in_at'] ) ) {
		$slots_grouped[ $slot_id ]['checked_in']++;
	}
}

uasort(
	$slots_grouped,
	function ( $a, $b ) {
		return strcmp( $a['start_time'], $b['start_time'] );
	}
);

$date_label = date_i18n( get_option( 'date_format' ), strtotime( $date ) );
?>
<div class="wrap aardbei-admin-wrap">
	<div class="aardbei-page-header">
		<div>
			<h1><?php echo esc_html__( 'Kassa', 'aardbei-reserveringen' ); ?></h1>
			<p class="aardbei-page-subtitle"><?php echo esc_html( $date_label ); ?></p>
		</div>
	</div>

	<!-- Datumselectie -->
	<div class="aardbei-form-card">
		<form method="get" class="aardbei-filter-form">
			<input type="hidden" name="page" value="aardbei-reserveringen-kassa">
			<label>
				<?php echo esc_html__( 'Datum', 'aardbei-reserveringen' ); ?>
				<input type="date" name="date" value="<?php echo esc_attr( $date ); ?>">
			</label>
			<button type="submit" class="aardbei-btn aardbei-btn--primary">
				<?php echo esc_html__( 'Toon', 'aardbei-reserveringen' ); ?>
			</button>
			<?php if ( $date !== $today ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aardbei-reserveringen-kassa' ) ); ?>" class="aardbei-btn aardbei-btn--outline">
					<?php echo esc_html__( 'Vandaag', 'aardbei-reserveringen' ); ?>
				</a>
			<?php endif; ?>
		</form>
	</div>

	<?php if ( empty( $slots_grouped ) ) : ?>
		<div class="aardbei-form-card">
			<p style="margin:0;"><?php echo esc_html( sprintf( __( 'Geen reserveringen gevonden voor %s.', 'aardbei-reserveringen' ), $date_label ) ); ?></p>
		</div>
	<?php else : ?>

		<!-- Zoekbalk -->
		<div class="aardbei-toolbar">
			<div class="aardbei-toolbar-left">
				<div class="aardbei-search-input-wrap">
					<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
					<input type="text" id="aardbei-kassa-search" class="aardbei-search-input"
						placeholder="<?php echo esc_attr__( 'Zoek op naam of telefoon…', 'aardbei-reserveringen' ); ?>">
				</div>
			</div>
			<div class="aardbei-toolbar-right">
				<button type="button" id="aardbei-kassa-refresh" class="aardbei-btn aardbei-btn--outline">
					<svg viewBox="0 0 24 24" style="width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;">
						<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/>
						<path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
					</svg>
					<?php echo esc_html__( 'Vernieuwen', 'aardbei-reserveringen' ); ?>
				</button>
			</div>
		</div>

		<!-- Tijdslot blokken -->
		<?php foreach ( $slots_grouped as $slot_data ) : ?>
			<?php
			$all_checked_in = $slot_data['checked_in'] === $slot_data['total'] && $slot_data['total'] > 0;
			?>
			<div class="aardbei-kassa-slot" id="aardbei-kassa-slot-<?php echo esc_attr( $slot_data['start_time'] ); ?>">
				<div class="aardbei-kassa-slot-header">
					<span class="aardbei-kassa-slot-time">
						<strong><?php echo esc_html( substr( $slot_data['start_time'], 0, 5 ) . ' – ' . substr( $slot_data['end_time'], 0, 5 ) ); ?></strong>
					</span>
					<span class="aardbei-kassa-slot-progress <?php echo $all_checked_in ? 'is-complete' : ''; ?>">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %1$d: aangemeld, %2$d: totaal. */
								__( '%1$d / %2$d aangemeld', 'aardbei-reserveringen' ),
								$slot_data['checked_in'],
								$slot_data['total']
							)
						);
						?>
					</span>
				</div>

				<div class="aardbei-table-wrap">
					<table class="aardbei-table aardbei-kassa-table" id="aardbei-kassa-table-<?php echo esc_attr( $slot_data['start_time'] ); ?>">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Naam', 'aardbei-reserveringen' ); ?></th>
								<th><?php echo esc_html__( 'Telefoon', 'aardbei-reserveringen' ); ?></th>
								<th><?php echo esc_html__( 'Personen', 'aardbei-reserveringen' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'aardbei-reserveringen' ); ?></th>
								<th><?php echo esc_html__( 'Actie', 'aardbei-reserveringen' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $slot_data['reservations'] as $res ) : ?>
								<?php $is_checked_in = ! empty( $res['checked_in_at'] ); ?>
								<tr class="aardbei-kassa-row <?php echo $is_checked_in ? 'is-checked-in' : ''; ?>"
									data-id="<?php echo esc_attr( $res['id'] ); ?>"
									data-search="<?php echo esc_attr( strtolower( $res['name'] . ' ' . $res['phone'] ) ); ?>">

									<td>
										<strong><?php echo esc_html( $res['name'] ); ?></strong>
										<?php if ( $res['note'] ) : ?>
											<small class="aardbei-muted-text"><?php echo esc_html( $res['note'] ); ?></small>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $res['phone'] ) : ?>
											<a href="tel:<?php echo esc_attr( $res['phone'] ); ?>" class="aardbei-muted-text"><?php echo esc_html( $res['phone'] ); ?></a>
										<?php else : ?>
											<span class="aardbei-muted-text">—</span>
										<?php endif; ?>
									</td>
									<td class="aardbei-num"><?php echo esc_html( number_format_i18n( $res['persons'] ) ); ?></td>
									<td class="aardbei-kassa-status-cell">
										<?php if ( $is_checked_in ) : ?>
											<span class="aardbei-status aardbei-status-checked-in">
												<svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:3;stroke-linecap:round;stroke-linejoin:round;vertical-align:middle;margin-right:3px;"><polyline points="20 6 9 17 4 12"/></svg>
												<?php echo esc_html( __( 'Aangemeld', 'aardbei-reserveringen' ) . ' ' . date_i18n( 'H:i', strtotime( $res['checked_in_at'] ) ) ); ?>
											</span>
										<?php else : ?>
											<span class="aardbei-status aardbei-status-confirmed">
												<?php echo esc_html__( 'Verwacht', 'aardbei-reserveringen' ); ?>
											</span>
										<?php endif; ?>
									</td>
									<td class="aardbei-kassa-action-cell">
										<?php if ( $is_checked_in ) : ?>
											<button type="button"
												class="aardbei-btn aardbei-btn--outline aardbei-btn--sm aardbei-kassa-undo"
												data-id="<?php echo esc_attr( $res['id'] ); ?>">
												<?php echo esc_html__( 'Ongedaan', 'aardbei-reserveringen' ); ?>
											</button>
										<?php else : ?>
											<button type="button"
												class="aardbei-btn aardbei-btn--success aardbei-btn--sm aardbei-kassa-checkin"
												data-id="<?php echo esc_attr( $res['id'] ); ?>">
												<svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:3;stroke-linecap:round;stroke-linejoin:round;"><polyline points="20 6 9 17 4 12"/></svg>
												<?php echo esc_html__( 'Aanmelden', 'aardbei-reserveringen' ); ?>
											</button>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

<style>
.aardbei-kassa-slot {
	margin-bottom: 24px;
}
.aardbei-kassa-slot-header {
	display: flex;
	align-items: center;
	gap: 16px;
	padding: 10px 16px;
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-bottom: none;
	border-radius: 8px 8px 0 0;
}
.aardbei-kassa-slot-header .aardbei-kassa-slot-time strong {
	font-size: 15px;
	color: #0f172a;
}
.aardbei-kassa-slot-progress {
	font-size: 13px;
	color: #64748b;
	background: #e2e8f0;
	padding: 2px 10px;
	border-radius: 20px;
}
.aardbei-kassa-slot-progress.is-complete {
	background: #dcfce7;
	color: #15803d;
	font-weight: 600;
}
.aardbei-kassa-slot .aardbei-table-wrap {
	border-radius: 0 0 8px 8px;
	border-top: none;
}
.aardbei-kassa-table .aardbei-kassa-row.is-checked-in td {
	background: #f0fdf4;
	color: #166534;
}
.aardbei-kassa-table .aardbei-kassa-row.is-checked-in strong {
	color: #166534;
}
.aardbei-status-checked-in {
	background: #dcfce7;
	color: #15803d;
	padding: 3px 10px;
	border-radius: 20px;
	font-size: 12px;
	font-weight: 600;
	white-space: nowrap;
}
.aardbei-kassa-row[style*="display: none"] {
	display: none !important;
}
</style>
