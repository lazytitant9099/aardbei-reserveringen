<?php
/**
 * Calendar view.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap aardbei-admin-wrap">
	<h1><?php echo esc_html__( 'Kalender', 'aardbei-reserveringen' ); ?></h1>
	<p style="color:#64748b;margin-top:-4px;"><?php echo esc_html__( 'Klik op een tijdslot om bezetting, status en reserveringen te bekijken.', 'aardbei-reserveringen' ); ?></p>

	<div class="aardbei-admin-calendar-layout">
		<div id="aardbei-admin-calendar"></div>

		<aside class="aardbei-admin-slot-panel" id="aardbei-admin-slot-panel" aria-live="polite">
			<h2><?php echo esc_html__( 'Tijdslot details', 'aardbei-reserveringen' ); ?></h2>
			<p class="aardbei-panel-empty"><?php echo esc_html__( 'Klik op een tijdslot in de kalender.', 'aardbei-reserveringen' ); ?></p>
			<div id="aardbei-admin-slot-details"></div>
		</aside>
	</div>
</div>
