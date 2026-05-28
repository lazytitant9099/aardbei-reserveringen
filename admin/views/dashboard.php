<?php
/**
 * Dashboard view.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$reservations_obj = new Aardbei_Reserveringen_Reservations();
$stats            = $reservations_obj->get_dashboard_stats();
$recent           = $reservations_obj->get_recent_reservations( 10 );
$trend            = $reservations_obj->get_weekly_comparison();

$slots_obj        = new Aardbei_Reserveringen_Slots();
$today_date       = date_i18n( 'Y-m-d', current_time( 'timestamp' ) );
$today_slots      = $slots_obj->get_slots( $today_date, $today_date, false );
$today_label      = date_i18n( get_option( 'date_format' ), current_time( 'timestamp' ) );

$total_capacity     = max( 0, (int) $stats['total_capacity'] );
$booked_persons     = max( 0, (int) $stats['booked_persons'] );
$remaining_capacity = max( 0, (int) $stats['remaining_capacity'] );
$occupancy_pct      = $total_capacity > 0 ? min( 100, round( $booked_persons / $total_capacity * 100 ) ) : 0;

$remaining_label = sprintf(
	/* translators: %s: remaining capacity. */
	_n( '%s plek beschikbaar', '%s plekken beschikbaar', $remaining_capacity, 'aardbei-reserveringen' ),
	number_format_i18n( $remaining_capacity )
);

$today_summary = array(
	'slots'        => count( $today_slots ),
	'open_slots'   => 0,
	'booked'       => 0,
	'capacity'     => 0,
	'full_slots'   => 0,
	'closed_slots' => 0,
);

foreach ( $today_slots as $slot ) {
	$slot_capacity  = max( 0, (int) $slot['capacity'] );
	$slot_booked    = max( 0, (int) $slot['booked_persons'] );
	$slot_remaining = max( 0, (int) $slot['remaining'] );
	$is_closed      = 'closed' === $slot['status'] || (int) $slot['manual_closed'];

	$today_summary['capacity'] += $slot_capacity;
	$today_summary['booked']   += $slot_booked;

	if ( $is_closed ) {
		$today_summary['closed_slots']++;
	} elseif ( $slot_remaining <= 0 && $slot_capacity > 0 ) {
		$today_summary['full_slots']++;
	} else {
		$today_summary['open_slots']++;
	}
}

$today_occupancy_pct = $today_summary['capacity'] > 0 ? min( 100, round( $today_summary['booked'] / $today_summary['capacity'] * 100 ) ) : 0;
$today_slots_label   = sprintf(
	/* translators: %s: amount of slots. */
	_n( '%s tijdslot', '%s tijdsloten', $today_summary['slots'], 'aardbei-reserveringen' ),
	number_format_i18n( $today_summary['slots'] )
);

$range_label = sprintf(
	/* translators: 1: start date, 2: end date. */
	__( 'Boekbare periode: %1$s – %2$s', 'aardbei-reserveringen' ),
	date_i18n( get_option( 'date_format' ), strtotime( $stats['range']['start'] ) ),
	date_i18n( get_option( 'date_format' ), strtotime( $stats['range']['end'] ) )
);

$kpi_cards = array(
	array(
		'label'   => __( 'Geboekte personen', 'aardbei-reserveringen' ),
		'value'   => $booked_persons,
		'icon'    => 'blue',
		'svg'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
		'trend'   => $trend,
		'primary' => true,
	),
	array(
		'label'   => __( 'Totale capaciteit', 'aardbei-reserveringen' ),
		'value'   => $total_capacity,
		'icon'    => 'purple',
		'svg'     => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
		'trend'   => null,
		'primary' => false,
	),
	array(
		'label'   => __( 'Resterende plaatsen', 'aardbei-reserveringen' ),
		'value'   => $remaining_capacity,
		'icon'    => 'green',
		'svg'     => '<polyline points="20 6 9 17 4 12"/>',
		'trend'   => null,
		'primary' => false,
	),
	array(
		'label'   => __( 'Reserveringen', 'aardbei-reserveringen' ),
		'value'   => $stats['reservation_count'],
		'icon'    => 'blue',
		'svg'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
		'trend'   => null,
		'primary' => false,
	),
	array(
		'label'   => __( 'Volle tijdsloten', 'aardbei-reserveringen' ),
		'value'   => $stats['full_slots'],
		'icon'    => 'orange',
		'svg'     => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
		'trend'   => null,
		'primary' => false,
	),
	array(
		'label'   => __( 'Gesloten tijdsloten', 'aardbei-reserveringen' ),
		'value'   => $stats['closed_slots'],
		'icon'    => 'slate',
		'svg'     => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
		'trend'   => null,
		'primary' => false,
	),
	array(
		'label'   => __( 'Annuleringen', 'aardbei-reserveringen' ),
		'value'   => $stats['cancelled_count'],
		'icon'    => 'red',
		'svg'     => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
		'trend'   => null,
		'primary' => false,
	),
);
?>
<div class="wrap aardbei-admin-wrap">
	<div class="aardbei-page-header">
		<div>
			<h1><?php echo esc_html__( 'Dashboard', 'aardbei-reserveringen' ); ?></h1>
			<p class="aardbei-page-subtitle"><?php echo esc_html( $range_label ); ?></p>
		</div>
		<div class="aardbei-quick-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=aardbei-reserveringen-slots' ) ); ?>" class="aardbei-btn aardbei-btn--primary">
				<svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
				<?php echo esc_html__( 'Tijdslot toevoegen', 'aardbei-reserveringen' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=aardbei-reserveringen-reservations' ) ); ?>" class="aardbei-btn aardbei-btn--outline">
				<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
				<?php echo esc_html__( 'Reserveringen', 'aardbei-reserveringen' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=aardbei-reserveringen-calendar' ) ); ?>" class="aardbei-btn aardbei-btn--outline">
				<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
				<?php echo esc_html__( 'Kalender', 'aardbei-reserveringen' ); ?>
			</a>
		</div>
	</div>

	<div class="aardbei-dashboard-overview">
		<section class="aardbei-overview-card aardbei-overview-card--capacity">
			<div class="aardbei-overview-eyebrow"><?php echo esc_html__( 'Boekbare periode', 'aardbei-reserveringen' ); ?></div>
			<div class="aardbei-overview-main">
				<div>
					<h2 class="aardbei-overview-title"><?php echo esc_html__( 'Bezetting', 'aardbei-reserveringen' ); ?></h2>
					<p class="aardbei-overview-subtitle">
						<?php
						printf(
							/* translators: 1: booked persons, 2: total capacity. */
							esc_html__( '%1$s van %2$s plaatsen geboekt', 'aardbei-reserveringen' ),
							esc_html( number_format_i18n( $booked_persons ) ),
							esc_html( number_format_i18n( $total_capacity ) )
						);
						?>
					</p>
				</div>
				<strong class="aardbei-overview-percent"><?php echo esc_html( $occupancy_pct ); ?>%</strong>
			</div>
			<div
				class="aardbei-overview-progress"
				role="progressbar"
				aria-valuemin="0"
				aria-valuemax="100"
				aria-valuenow="<?php echo esc_attr( $occupancy_pct ); ?>"
				aria-label="<?php echo esc_attr__( 'Bezetting in de boekbare periode', 'aardbei-reserveringen' ); ?>">
				<span style="width:<?php echo esc_attr( $occupancy_pct ); ?>%"></span>
			</div>
			<div class="aardbei-overview-metrics">
				<div class="aardbei-overview-metric">
					<strong><?php echo esc_html( $remaining_label ); ?></strong>
					<span><?php echo esc_html__( 'Nog vrij', 'aardbei-reserveringen' ); ?></span>
				</div>
				<div class="aardbei-overview-metric">
					<strong><?php echo esc_html( number_format_i18n( $stats['reservation_count'] ) ); ?></strong>
					<span><?php echo esc_html__( 'Actieve reserveringen', 'aardbei-reserveringen' ); ?></span>
				</div>
				<div class="aardbei-overview-metric">
					<strong><?php echo esc_html( number_format_i18n( $stats['full_slots'] + $stats['closed_slots'] ) ); ?></strong>
					<span><?php echo esc_html__( 'Vol of gesloten', 'aardbei-reserveringen' ); ?></span>
				</div>
			</div>
		</section>

		<section class="aardbei-overview-card">
			<div class="aardbei-overview-card-header">
				<div>
					<div class="aardbei-overview-eyebrow"><?php echo esc_html__( 'Vandaag', 'aardbei-reserveringen' ); ?></div>
					<h2 class="aardbei-overview-title aardbei-overview-title--sm"><?php echo esc_html( $today_label ); ?></h2>
				</div>
				<span class="aardbei-pill"><?php echo esc_html( $today_slots_label ); ?></span>
			</div>

			<?php if ( ! empty( $today_slots ) ) : ?>
				<p class="aardbei-overview-subtitle">
					<?php
					printf(
						/* translators: 1: booked persons today, 2: today's capacity. */
						esc_html__( '%1$s van %2$s plekken voor vandaag bezet', 'aardbei-reserveringen' ),
						esc_html( number_format_i18n( $today_summary['booked'] ) ),
						esc_html( number_format_i18n( $today_summary['capacity'] ) )
					);
					?>
				</p>
				<div
					class="aardbei-overview-progress aardbei-overview-progress--today"
					role="progressbar"
					aria-valuemin="0"
					aria-valuemax="100"
					aria-valuenow="<?php echo esc_attr( $today_occupancy_pct ); ?>"
					aria-label="<?php echo esc_attr__( 'Bezetting vandaag', 'aardbei-reserveringen' ); ?>">
					<span style="width:<?php echo esc_attr( $today_occupancy_pct ); ?>%"></span>
				</div>
				<div class="aardbei-overview-metrics aardbei-overview-metrics--compact">
					<div class="aardbei-overview-metric">
						<strong><?php echo esc_html( number_format_i18n( $today_summary['open_slots'] ) ); ?></strong>
						<span><?php echo esc_html__( 'Open', 'aardbei-reserveringen' ); ?></span>
					</div>
					<div class="aardbei-overview-metric">
						<strong><?php echo esc_html( number_format_i18n( $today_summary['full_slots'] ) ); ?></strong>
						<span><?php echo esc_html__( 'Vol', 'aardbei-reserveringen' ); ?></span>
					</div>
					<div class="aardbei-overview-metric">
						<strong><?php echo esc_html( number_format_i18n( $today_summary['closed_slots'] ) ); ?></strong>
						<span><?php echo esc_html__( 'Gesloten', 'aardbei-reserveringen' ); ?></span>
					</div>
				</div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aardbei-reserveringen-calendar' ) ); ?>" class="aardbei-btn aardbei-btn--outline aardbei-btn--block">
					<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
					<?php echo esc_html__( 'Bekijk kalender', 'aardbei-reserveringen' ); ?>
				</a>
			<?php else : ?>
				<p class="aardbei-empty-state-copy"><?php echo esc_html__( 'Er staan vandaag geen tijdsloten klaar.', 'aardbei-reserveringen' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aardbei-reserveringen-slots' ) ); ?>" class="aardbei-btn aardbei-btn--primary aardbei-btn--block">
					<svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
					<?php echo esc_html__( 'Tijdslot toevoegen', 'aardbei-reserveringen' ); ?>
				</a>
			<?php endif; ?>
		</section>
	</div>

	<?php if ( ! empty( $today_slots ) ) : ?>
	<div class="aardbei-today-section">
		<div class="aardbei-section-header">
			<h2 class="aardbei-section-title">
				<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
				<?php echo esc_html__( 'Vandaag per tijdslot', 'aardbei-reserveringen' ); ?>
			</h2>
			<span class="aardbei-section-meta">
				<?php
				printf(
					/* translators: 1: open slots, 2: total slots. */
					esc_html__( '%1$s open van %2$s', 'aardbei-reserveringen' ),
					esc_html( number_format_i18n( $today_summary['open_slots'] ) ),
					esc_html( number_format_i18n( $today_summary['slots'] ) )
				);
				?>
			</span>
		</div>
		<div class="aardbei-today-slots">
			<?php foreach ( $today_slots as $slot ) :
				$capacity  = max( 1, (int) $slot['capacity'] );
				$booked    = (int) $slot['booked_persons'];
				$remaining = (int) $slot['remaining'];
				$is_closed = 'closed' === $slot['status'] || (int) $slot['manual_closed'];
				$fill_pct  = min( 100, round( $booked / $capacity * 100 ) );
				$fill_cls  = $fill_pct >= 90 ? 'high' : ( $fill_pct >= 60 ? 'medium' : '' );

				if ( $is_closed ) {
					$badge = 'closed';
					$badge_label = __( 'Gesloten', 'aardbei-reserveringen' );
				} elseif ( $remaining <= 0 ) {
					$badge = 'full';
					$badge_label = __( 'Vol', 'aardbei-reserveringen' );
				} else {
					$badge = 'open';
					$badge_label = __( 'Open', 'aardbei-reserveringen' );
				}
			?>
			<div class="aardbei-today-slot">
				<div class="aardbei-today-slot-time">
					<?php echo esc_html( substr( $slot['start_time'], 0, 5 ) . ' – ' . substr( $slot['end_time'], 0, 5 ) ); ?>
				</div>
				<div class="aardbei-capacity-wrap">
					<div class="aardbei-capacity-bar">
						<div class="aardbei-capacity-fill aardbei-capacity-fill--<?php echo esc_attr( $fill_cls ); ?>" style="width:<?php echo esc_attr( $fill_pct ); ?>%"></div>
					</div>
					<span class="aardbei-capacity-label"><?php echo esc_html( $booked . '/' . $capacity ); ?></span>
				</div>
				<span class="aardbei-status aardbei-status-<?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $badge_label ); ?></span>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<div class="aardbei-kpi-grid">
		<?php foreach ( $kpi_cards as $card ) : ?>
			<div class="aardbei-kpi-card aardbei-kpi-card--<?php echo esc_attr( $card['icon'] ); ?>">
				<div class="aardbei-kpi-icon aardbei-kpi-icon--<?php echo esc_attr( $card['icon'] ); ?>">
					<svg viewBox="0 0 24 24"><?php echo $card['svg']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></svg>
				</div>
				<div class="aardbei-kpi-body">
					<div class="aardbei-kpi-label"><?php echo esc_html( $card['label'] ); ?></div>
					<div class="aardbei-kpi-value"><?php echo esc_html( number_format_i18n( $card['value'] ) ); ?></div>
					<?php if ( $card['primary'] && $trend ) : ?>
						<div class="aardbei-kpi-trend aardbei-kpi-trend--<?php echo esc_attr( $trend['trend'] ); ?>">
							<?php if ( 'up' === $trend['trend'] ) : ?>
								<svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>
								+<?php echo esc_html( $trend['diff'] ); ?> <?php echo esc_html__( 'vs vorige week', 'aardbei-reserveringen' ); ?>
							<?php elseif ( 'down' === $trend['trend'] ) : ?>
								<svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
								-<?php echo esc_html( $trend['diff'] ); ?> <?php echo esc_html__( 'vs vorige week', 'aardbei-reserveringen' ); ?>
							<?php else : ?>
								<?php echo esc_html__( 'Gelijk aan vorige week', 'aardbei-reserveringen' ); ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="aardbei-section-header">
		<h2 class="aardbei-section-title">
			<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
			<?php echo esc_html__( 'Recente reserveringen', 'aardbei-reserveringen' ); ?>
		</h2>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=aardbei-reserveringen-reservations' ) ); ?>" class="aardbei-btn aardbei-btn--outline aardbei-btn--sm">
			<?php echo esc_html__( 'Alle reserveringen', 'aardbei-reserveringen' ); ?>
		</a>
	</div>
	<div class="aardbei-table-wrap">
		<table class="aardbei-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Datum & Tijd', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Naam', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'E-mail', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Personen', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Actie', 'aardbei-reserveringen' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $recent ) ) : ?>
					<tr><td colspan="6" class="aardbei-table-empty"><?php echo esc_html__( 'Nog geen reserveringen.', 'aardbei-reserveringen' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $recent as $reservation ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $reservation['date'] ) ) ); ?></strong>
								<small class="aardbei-muted-text"><?php echo esc_html( substr( $reservation['start_time'], 0, 5 ) . ' – ' . substr( $reservation['end_time'], 0, 5 ) ); ?></small>
							</td>
							<td><?php echo esc_html( $reservation['name'] ); ?></td>
							<td class="aardbei-col-email">
								<a href="mailto:<?php echo esc_attr( $reservation['email'] ); ?>" class="aardbei-link">
									<?php echo esc_html( $reservation['email'] ); ?>
								</a>
							</td>
							<td class="aardbei-num"><?php echo esc_html( number_format_i18n( $reservation['persons'] ) ); ?></td>
							<td><span class="aardbei-status aardbei-status-<?php echo esc_attr( $reservation['status'] ); ?>"><?php echo esc_html( 'confirmed' === $reservation['status'] ? __( 'Bevestigd', 'aardbei-reserveringen' ) : __( 'Geannuleerd', 'aardbei-reserveringen' ) ); ?></span></td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=aardbei-reserveringen-reservations' ) ); ?>" class="aardbei-btn aardbei-btn--outline aardbei-btn--sm">
									<?php echo esc_html__( 'Bekijk', 'aardbei-reserveringen' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
