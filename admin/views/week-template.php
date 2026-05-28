<?php
/**
 * Week template view.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$templates_table = Aardbei_Reserveringen_Database::get_week_templates_table();
$templates       = $wpdb->get_results( "SELECT * FROM {$templates_table} ORDER BY weekday ASC, start_time ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$weekdays        = Aardbei_Reserveringen_Admin::get_weekday_options();

// Groepeer templates per weekdag voor de visuele weergave
$by_day = array();
for ( $d = 1; $d <= 7; $d++ ) {
	$by_day[ $d ] = array();
}
foreach ( $templates as $tpl ) {
	$by_day[ (int) $tpl['weekday'] ][] = $tpl;
}
?>
<div class="wrap aardbei-admin-wrap">
	<h1><?php echo esc_html__( 'Weekschema', 'aardbei-reserveringen' ); ?></h1>

	<!-- Visuele weekweergave -->
	<?php if ( ! empty( $templates ) ) : ?>
		<h2><?php echo esc_html__( 'Huidig schema', 'aardbei-reserveringen' ); ?></h2>
		<div class="aardbei-week-visual">
			<?php foreach ( $weekdays as $day_num => $day_label ) : ?>
				<div>
					<div class="aardbei-week-col-label"><?php echo esc_html( mb_substr( $day_label, 0, 2 ) ); ?></div>
					<div class="aardbei-week-col-body">
						<?php foreach ( $by_day[ $day_num ] as $tpl ) : ?>
							<div class="aardbei-week-pill<?php echo ! (int) $tpl['active'] ? ' aardbei-week-pill--inactive' : ''; ?>"
								title="<?php echo esc_attr( sprintf( '%s – %s, %d pers.', substr( $tpl['start_time'], 0, 5 ), substr( $tpl['end_time'], 0, 5 ), $tpl['capacity'] ) ); ?>">
								<?php echo esc_html( substr( $tpl['start_time'], 0, 5 ) ); ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<!-- Nieuwe regel toevoegen -->
	<div class="aardbei-form-card">
		<h3><?php echo esc_html__( 'Nieuwe regel toevoegen', 'aardbei-reserveringen' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aardbei-inline-form">
			<input type="hidden" name="action" value="aardbei_add_week_template">
			<?php wp_nonce_field( 'aardbei_add_week_template' ); ?>
			<label>
				<?php echo esc_html__( 'Weekdag', 'aardbei-reserveringen' ); ?>
				<select name="weekday" required>
					<?php foreach ( $weekdays as $number => $label ) : ?>
						<option value="<?php echo esc_attr( $number ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<?php echo esc_html__( 'Open vanaf', 'aardbei-reserveringen' ); ?>
				<input type="time" name="start_time" required>
			</label>
			<label>
				<?php echo esc_html__( 'Dicht om', 'aardbei-reserveringen' ); ?>
				<input type="time" name="end_time" required>
			</label>
			<label>
				<?php echo esc_html__( 'Tijdslotduur', 'aardbei-reserveringen' ); ?>
				<select name="slot_duration_minutes">
					<option value="15">15 min</option>
					<option value="30" selected>30 min</option>
					<option value="45">45 min</option>
					<option value="60">60 min</option>
					<option value="90">90 min</option>
					<option value="120">120 min</option>
				</select>
			</label>
			<label>
				<?php echo esc_html__( 'Capaciteit', 'aardbei-reserveringen' ); ?>
				<input type="number" name="capacity" min="1" required style="width:80px;">
			</label>
			<label class="aardbei-checkbox">
				<input type="checkbox" name="active" value="1" checked>
				<?php echo esc_html__( 'Actief', 'aardbei-reserveringen' ); ?>
			</label>
			<button type="submit" class="aardbei-btn aardbei-btn--primary">
				<svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
				<?php echo esc_html__( 'Toevoegen', 'aardbei-reserveringen' ); ?>
			</button>
		</form>
	</div>

	<!-- Bestaande regels -->
	<h2><?php echo esc_html__( 'Bestaande regels', 'aardbei-reserveringen' ); ?></h2>
	<div class="aardbei-table-wrap">
		<table class="aardbei-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Weekdag', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Tijdvenster', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Slotduur', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Capaciteit', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Actief', 'aardbei-reserveringen' ); ?></th>
					<th><?php echo esc_html__( 'Actie', 'aardbei-reserveringen' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $templates ) ) : ?>
					<tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:40px;"><?php echo esc_html__( 'Nog geen weekschema regels.', 'aardbei-reserveringen' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $templates as $template ) : ?>
						<tr>
							<td><strong><?php echo esc_html( isset( $weekdays[ (int) $template['weekday'] ] ) ? $weekdays[ (int) $template['weekday'] ] : $template['weekday'] ); ?></strong></td>
							<td><?php echo esc_html( substr( $template['start_time'], 0, 5 ) . ' – ' . substr( $template['end_time'], 0, 5 ) ); ?></td>
							<td>
								<?php
								printf(
									/* translators: %d: minutes. */
									esc_html__( '%d min', 'aardbei-reserveringen' ),
									isset( $template['slot_duration_minutes'] ) ? absint( $template['slot_duration_minutes'] ) : 60
								);
								?>
							</td>
							<td><?php echo esc_html( number_format_i18n( $template['capacity'] ) ); ?></td>
							<td>
								<span class="aardbei-status aardbei-status-<?php echo (int) $template['active'] ? 'open' : 'closed'; ?>">
									<?php echo (int) $template['active'] ? esc_html__( 'Actief', 'aardbei-reserveringen' ) : esc_html__( 'Inactief', 'aardbei-reserveringen' ); ?>
								</span>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="aardbei_delete_week_template">
									<input type="hidden" name="id" value="<?php echo esc_attr( $template['id'] ); ?>">
									<?php wp_nonce_field( 'aardbei_delete_week_template_' . absint( $template['id'] ) ); ?>
									<button type="submit" class="aardbei-btn aardbei-btn--danger aardbei-btn--sm">
										<svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
										<?php echo esc_html__( 'Verwijderen', 'aardbei-reserveringen' ); ?>
									</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- Tijdsloten genereren -->
	<div class="aardbei-form-card" style="margin-top:24px;">
		<h3><?php echo esc_html__( 'Tijdsloten genereren', 'aardbei-reserveringen' ); ?></h3>
		<p style="margin:0 0 14px;font-size:13px;color:#475569;"><?php echo esc_html__( 'Genereer tijdsloten voor de volgende boekbare week op basis van dit weekschema. De dagelijkse cron doet dit automatisch.', 'aardbei-reserveringen' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="aardbei_generate_next_week_slots">
			<?php wp_nonce_field( 'aardbei_generate_next_week_slots' ); ?>
			<button type="submit" class="aardbei-btn aardbei-btn--primary">
				<svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
				<?php echo esc_html__( 'Genereer tijdsloten voor volgende week', 'aardbei-reserveringen' ); ?>
			</button>
		</form>
	</div>
</div>
