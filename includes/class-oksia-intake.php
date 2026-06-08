<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OKSIA_Intake
 *
 * Handles the public client intake form:
 *   - Shortcode  [oksia_intake_form]
 *   - AJAX submission -> creates oksia_itinerary draft post
 *   - Assigns Quote ID (same sequence logic as admin)
 *   - Emails the agent on every new submission
 */
class OKSIA_Intake {

	public function __construct() {
		add_shortcode( 'oksia_intake_form', array( $this, 'render_shortcode' ) );
		add_shortcode( 'oksia_agent_intake_form', array( $this, 'render_agent_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_nopriv_oksia_submit_intake', array( $this, 'handle_submission' ) );
		add_action( 'wp_ajax_oksia_submit_intake', array( $this, 'handle_submission' ) );
		add_action( 'admin_post_oksia_submit_agent_workspace', array( $this, 'handle_agent_submission' ) );
		add_action( 'admin_post_nopriv_oksia_submit_agent_workspace', array( $this, 'redirect_agent_login' ) );
	}

	/* -------------------------------------------------------------------------
	 * Assets
	 * ---------------------------------------------------------------------- */

	public function enqueue_assets() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ( ! has_shortcode( $post->post_content, 'oksia_intake_form' ) && ! has_shortcode( $post->post_content, 'oksia_agent_intake_form' ) ) ) {
			return;
		}

		if ( has_shortcode( $post->post_content, 'oksia_agent_intake_form' ) ) {
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_media();
			wp_enqueue_style(
				'oksia-admin',
				OKSIA_URL . 'assets/css/admin.css',
				array(),
				OKSIA_VERSION
			);
			wp_enqueue_style(
				'oksia-intake',
				OKSIA_URL . 'assets/css/intake.css',
				array( 'oksia-admin' ),
				OKSIA_VERSION
			);
			wp_enqueue_script(
				'oksia-admin',
				OKSIA_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				OKSIA_VERSION,
				true
			);
			wp_localize_script(
				'oksia-admin',
				'okAdminData',
				array(
					'destinations' => array(
						'Domestic' => $this->get_destination_list( 'oksia_domestic_destinations' ),
						'International' => $this->get_destination_list( 'oksia_international_destinations' ),
					),
					'tripOptions' => array(
						'hotel_categories' => $this->get_trip_type_setting_options(
							'oksia_hotel_categories_domestic',
							'oksia_hotel_categories_international',
							array( '3 Star', '4 Star', '5 Star', '3/4 Split', '3/5 Split', '4/5 Split' )
						),
						'occupancies' => $this->get_trip_type_setting_options(
							'oksia_occupancies_domestic',
							'oksia_occupancies_international',
							array( 'Single', 'Double', 'Triple', 'Quad' )
						),
						'meal_plans' => $this->get_trip_type_setting_options(
							'oksia_meal_plans_domestic',
							'oksia_meal_plans_international',
							array( 'No Meals', 'Breakfast', 'Breakfast & Dinner', 'Breakfast/Lunch/Dinner', 'Breakfast/Lunch/HiTea/Dinner' )
						),
						'pickup_points' => $this->get_trip_type_setting_options(
							'oksia_pickup_points_domestic',
							'oksia_pickup_points_international',
							array()
						),
						'drop_points' => $this->get_trip_type_setting_options(
							'oksia_drop_points_domestic',
							'oksia_drop_points_international',
							array()
						),
						'transfer_modes' => $this->get_trip_type_setting_options(
							'oksia_transfer_modes_domestic',
							'oksia_transfer_modes_international',
							array( 'Private', 'SIC - Sharing in Coach' )
						),
						'sightseeing_vehicles' => $this->get_trip_type_setting_options(
							'oksia_sightseeing_vehicles_domestic',
							'oksia_sightseeing_vehicles_international',
							array( 'Private', 'SIC - Sharing in Coach' )
						),
						'vehicle_types' => $this->get_trip_type_setting_options(
							'oksia_vehicle_types_domestic',
							'oksia_vehicle_types_international',
							array( 'Tempo Traveller', 'Innova', 'Sedan', 'SUV', 'Coach', 'Minibus' )
						),
					),
					'exchangeApiBase' => 'https://convertz.app/api/currency',
					'currencyRatesInr' => $this->get_currency_snapshot_rates_inr(),
				)
			);
			return;
		}

		wp_enqueue_style(
			'oksia-intake',
			OKSIA_URL . 'assets/css/intake.css',
			array(),
			OKSIA_VERSION
		);

		wp_enqueue_script(
			'oksia-intake',
			OKSIA_URL . 'assets/js/intake.js',
			array( 'jquery' ),
			OKSIA_VERSION,
			true
		);

		wp_localize_script(
			'oksia-intake',
			'okIntake',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'oksia_intake_nonce' ),
				'destinations' => array(
					'Domestic'      => $this->get_destination_list( 'oksia_domestic_destinations' ),
					'International' => $this->get_destination_list( 'oksia_international_destinations' ),
				),
				'agencyName'   => get_option( 'oksia_agency_name', 'OK' ),
				'agencyTagline' => get_option( 'oksia_intake_tagline', 'Tell us about your dream trip' ),
			)
		);
	}

	/* -------------------------------------------------------------------------
	 * Shortcode
	 * ---------------------------------------------------------------------- */

	public function render_shortcode( $atts = array() ) {
		return $this->render_form_markup( 'public' );
	}

	public function render_agent_shortcode( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return $this->render_agent_workspace_markup();
		}

		$mode     = isset( $_GET['oksia_agent_mode'] ) ? sanitize_text_field( wp_unslash( $_GET['oksia_agent_mode'] ) ) : '';
		$quote_id = isset( $_GET['oksia_quote_id'] ) ? sanitize_text_field( wp_unslash( $_GET['oksia_quote_id'] ) ) : '';
		$post_id  = 0;

		if ( 'open' === $mode && $quote_id ) {
			$post_id = $this->find_submission_by_quote_id( $quote_id );
		}

		if ( 'new' === $mode ) {
			return $this->render_agent_workspace_markup();
		}

		if ( 'open' === $mode ) {
			if ( $quote_id && ! $post_id ) {
				return $this->render_agent_lookup_screen( $quote_id, __( 'No submission was found for that Quote ID.', 'oksia-smart-itinerary-agent' ) );
			}

			if ( $post_id ) {
				return $this->render_agent_workspace_markup( $post_id );
			}

			return $this->render_agent_lookup_screen( '', '' );
		}

		return $this->render_agent_home_screen();
	}

	private function render_agent_home_screen() {
		ob_start();
		?>
		<div class="oksia-intake-wrap oksia-agent-workspace" style="--oksia-intake-primary:#173f68; --oksia-intake-primary-dark:#132041; --oksia-intake-primary-bg:#eef5fb; --oksia-intake-primary-text:#173f68; --oksia-intake-accent:#1f7a8c; --oksia-intake-gold:#4aa39b;">
			<div class="oksia-intake-brand">
				<div class="oksia-intake-brand-name"><?php echo esc_html( get_option( 'oksia_agency_name', 'OK' ) ); ?></div>
				<div class="oksia-intake-tagline"><?php esc_html_e( 'Agent Workspace', 'oksia-smart-itinerary-agent' ); ?></div>
			</div>
			<div class="oksia-agent-launcher">
				<a class="button button-primary oksia-agent-launch-button" href="<?php echo esc_url( add_query_arg( 'oksia_agent_mode', 'open', $this->current_url() ) ); ?>"><?php esc_html_e( 'Open Client Submission', 'oksia-smart-itinerary-agent' ); ?></a>
				<a class="button button-primary oksia-agent-launch-button" href="<?php echo esc_url( add_query_arg( 'oksia_agent_mode', 'new', $this->current_url() ) ); ?>"><?php esc_html_e( 'Create New Submission', 'oksia-smart-itinerary-agent' ); ?></a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_agent_lookup_screen( $quote_id = '', $message = '' ) {
		ob_start();
		?>
		<div class="oksia-intake-wrap oksia-agent-workspace" style="--oksia-intake-primary:#173f68; --oksia-intake-primary-dark:#132041; --oksia-intake-primary-bg:#eef5fb; --oksia-intake-primary-text:#173f68; --oksia-intake-accent:#1f7a8c; --oksia-intake-gold:#4aa39b;">
			<div class="oksia-intake-brand">
				<div class="oksia-intake-brand-name"><?php echo esc_html( get_option( 'oksia_agency_name', 'OK' ) ); ?></div>
				<div class="oksia-intake-tagline"><?php esc_html_e( 'Open Existing Client Submission', 'oksia-smart-itinerary-agent' ); ?></div>
			</div>
			<div class="oksia-intake-card">
				<?php if ( $message ) : ?>
					<div class="oksia-intake-err" style="display:block"><?php echo esc_html( $message ); ?></div>
				<?php endif; ?>
				<form method="get" action="<?php echo esc_url( $this->current_url() ); ?>">
					<input type="hidden" name="oksia_agent_mode" value="open" />
					<div class="oksia-agent-launcher oksia-agent-launcher--stacked">
						<div class="oksia-intake-field">
							<label for="oksia_quote_id"><?php esc_html_e( 'Quote ID', 'oksia-smart-itinerary-agent' ); ?></label>
							<input type="text" id="oksia_quote_id" name="oksia_quote_id" value="<?php echo esc_attr( $quote_id ); ?>" placeholder="OK26040601" />
						</div>
						<div class="oksia-agent-launch-actions">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Open Submission', 'oksia-smart-itinerary-agent' ); ?></button>
							<a class="button" href="<?php echo esc_url( add_query_arg( 'oksia_agent_mode', 'new', $this->current_url() ) ); ?>"><?php esc_html_e( 'Create New Submission', 'oksia-smart-itinerary-agent' ); ?></a>
						</div>
					</div>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_form_markup( $mode = 'public' ) {
		ob_start();
		$agency_name    = esc_html( get_option( 'oksia_agency_name', 'OK' ) );
		$agency_tagline = esc_html( get_option( 'oksia_intake_tagline', 'Tell us about your dream trip' ) );
		$logo_url       = $this->get_brand_logo_src();
		$primary        = '#173f68';
		$is_agent       = 'agent' === $mode;
		?>
		<div class="oksia-intake-wrap" style="--oksia-intake-primary:<?php echo $primary; ?>; --oksia-intake-primary-dark:#132041; --oksia-intake-primary-bg:#eef5fb; --oksia-intake-primary-text:#173f68; --oksia-intake-accent:#1f7a8c; --oksia-intake-gold:#4aa39b;">

			<!-- Brand header -->
			<div class="oksia-intake-brand">
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_attr( $logo_url ); ?>" alt="<?php echo $agency_name; ?>" class="oksia-intake-logo" />
				<?php else : ?>
					<div class="oksia-intake-brand-name"><?php echo $agency_name; ?></div>
				<?php endif; ?>
				<div class="oksia-intake-tagline"><?php echo $agency_tagline; ?></div>
			</div>

			<div class="oksia-public-hero">
				<div class="oksia-public-hero__copy">
					<div class="oksia-public-hero__eyebrow"><?php esc_html_e( 'Quick trip request', 'oksia-smart-itinerary-agent' ); ?></div>
					<h2><?php esc_html_e( "Tell us your trip plan and we'll build the quote for you.", 'oksia-smart-itinerary-agent' ); ?></h2>
					<p><?php esc_html_e( 'This browser form is built for phones and desktops, so you can share it with clients without requiring any app installation.', 'oksia-smart-itinerary-agent' ); ?></p>
				</div>
				<div class="oksia-public-hero__notes" aria-label="<?php esc_attr_e( 'Form highlights', 'oksia-smart-itinerary-agent' ); ?>">
					<div class="oksia-public-note"><?php esc_html_e( 'No app needed', 'oksia-smart-itinerary-agent' ); ?></div>
					<div class="oksia-public-note"><?php esc_html_e( 'Mobile friendly', 'oksia-smart-itinerary-agent' ); ?></div>
					<div class="oksia-public-note"><?php esc_html_e( 'Fast quote handoff', 'oksia-smart-itinerary-agent' ); ?></div>
				</div>
			</div>

			<?php if ( $is_agent && ! is_user_logged_in() ) : ?>
				<div class="oksia-agent-login-card">
					<div class="oksia-agent-login-title"><?php esc_html_e( 'Agent login required', 'oksia-smart-itinerary-agent' ); ?></div>
					<div class="oksia-agent-login-text"><?php esc_html_e( 'Log in to submit requests into the main agency account.', 'oksia-smart-itinerary-agent' ); ?></div>
					<?php $this->render_agency_login_form( $this->current_url() ); ?>
				</div>
			<?php else : ?>

			<!-- Form card -->
			<form class="oksia-intake-form oksia-intake-card" id="oksia-intake-form-card" onsubmit="return false;">
				<input type="hidden" id="oksia-portal-mode" value="<?php echo esc_attr( $mode ); ?>" />

				<!-- Section: Your details -->
				<div class="oksia-intake-section-label">Your details</div>

				<div class="oksia-intake-grid-2 oksia-intake-grid-2--name-row">
					<div class="oksia-intake-field">
						<label for="oksia-salutation">Salutation</label>
						<select id="oksia-salutation">
							<option value="Mr">Mr</option>
							<option value="Ms">Ms</option>
							<option value="Mrs">Mrs</option>
						</select>
					</div>

					<div class="oksia-intake-field">
						<label for="oksia-name">Full name</label>
						<input type="text" id="oksia-name" placeholder="e.g. Rahul Sharma" autocomplete="name" />
					</div>
				</div>

				<div class="oksia-intake-grid-2">
					<div class="oksia-intake-field">
						<label for="oksia-phone">Phone number</label>
						<div class="oksia-phone-row">
							<div class="oksia-phone-prefix">+91</div>
							<input type="tel" id="oksia-phone" placeholder="98765 43210" maxlength="10" autocomplete="tel" />
						</div>
					</div>
					<div class="oksia-intake-field">
						<label for="oksia-email">Email address</label>
						<input type="email" id="oksia-email" placeholder="you@email.com" autocomplete="email" />
					</div>
				</div>

				<div class="oksia-intake-divider"></div>

				<!-- Section: Trip details -->
				<div class="oksia-intake-section-label">Trip details</div>

				<div class="oksia-intake-field">
					<label>Trip type</label>
					<div class="oksia-trip-type" id="oksia-trip-type">
						<div class="oksia-tt oksia-tt--on" data-type="Domestic">
							<div class="oksia-tt-name">Domestic</div>
							<div class="oksia-tt-sub">Within India</div>
						</div>
						<div class="oksia-tt" data-type="International">
							<div class="oksia-tt-name">International</div>
							<div class="oksia-tt-sub">Outside India</div>
						</div>
					</div>
				</div>

				<div class="oksia-intake-grid-2">
					<div class="oksia-intake-field">
						<label for="oksia-start-date">Start date</label>
						<input type="date" id="oksia-start-date" />
					</div>
					<div class="oksia-intake-field">
						<label for="oksia-end-date">End date</label>
						<input type="date" id="oksia-end-date" />
					</div>
				</div>

				<div class="oksia-intake-grid-2">
					<div class="oksia-intake-field">
						<label for="oksia-dest">Destination</label>
						<select id="oksia-dest">
							<option value="">Select destination</option>
						</select>
					</div>
					<div class="oksia-intake-field">
						<label for="oksia-nights">Total nights</label>
						<input type="number" id="oksia-nights" placeholder="Auto-calculated" min="1" max="60" readonly />
					</div>
				</div>

				<div class="oksia-intake-divider"></div>

				<!-- Section: Travellers -->
				<div class="oksia-intake-section-label">Travellers</div>

				<div class="oksia-pax-list">
					<div class="oksia-pax-row">
						<div class="oksia-pax-info">
							<div class="oksia-pax-name">Adults</div>
							<div class="oksia-pax-age">Age 12 and above</div>
						</div>
						<div class="oksia-pax-ctrl">
							<button type="button" class="oksia-pax-btn" data-key="adults" data-dir="-1">&#8722;</button>
							<div class="oksia-pax-val" id="oksia-pv-adults">1</div>
							<button type="button" class="oksia-pax-btn" data-key="adults" data-dir="1">+</button>
						</div>
					</div>
					<div class="oksia-pax-row">
						<div class="oksia-pax-info">
							<div class="oksia-pax-name">Children</div>
							<div class="oksia-pax-age">Age 6 to 11 &middot; chargeable with bed</div>
						</div>
						<div class="oksia-pax-ctrl">
							<button type="button" class="oksia-pax-btn" data-key="c611" data-dir="-1">&#8722;</button>
							<div class="oksia-pax-val" id="oksia-pv-c611">0</div>
							<button type="button" class="oksia-pax-btn" data-key="c611" data-dir="1">+</button>
						</div>
					</div>
					<div class="oksia-pax-row">
						<div class="oksia-pax-info">
							<div class="oksia-pax-name">Infants</div>
							<div class="oksia-pax-age">Below 5 years &middot; complimentary</div>
						</div>
						<div class="oksia-pax-ctrl">
							<button type="button" class="oksia-pax-btn" data-key="inf" data-dir="-1">&#8722;</button>
							<div class="oksia-pax-val" id="oksia-pv-inf">0</div>
							<button type="button" class="oksia-pax-btn" data-key="inf" data-dir="1">+</button>
						</div>
					</div>
				</div>

				<div class="oksia-intake-err" id="oksia-intake-err" style="display:none"></div>

				<button type="button" class="oksia-intake-submit" id="oksia-intake-submit">
					Get Quotation
				</button>

			</form>

			<?php endif; ?>

			<!-- Success card (hidden until submission) -->
			<div class="oksia-intake-success" id="oksia-intake-success" style="display:none">
				<div class="oksia-qid-badge" id="oksia-qid-out"></div>
				<div class="oksia-success-title">We&rsquo;ve got your request!</div>
				<div class="oksia-success-msg">
					Our agent will prepare a personalised itinerary for you shortly.<br>
					<strong>Save your Quote ID</strong> &mdash; you&rsquo;ll need it to track your trip.
				</div>
				<div class="oksia-success-details" id="oksia-success-details"></div>
			</div>

		</div><!-- /.oksia-intake-wrap -->
		<?php
		return ob_get_clean();
	}

	private function get_brand_logo_src() {
		$logo = trim( (string) get_option( 'oksia_agency_logo_url', '' ) );
		$cache_key = 'oksia_brand_logo_src_' . md5( $logo . '|' . get_current_blog_id() );
		$cached_logo = get_transient( $cache_key );
		if ( false !== $cached_logo && '' !== (string) $cached_logo ) {
			return (string) $cached_logo;
		}

		$resolved_logo = '';
		if ( '' !== $logo ) {
			$attachment_id = attachment_url_to_postid( $logo );
			if ( $attachment_id ) {
				$attachment_url = wp_get_attachment_url( $attachment_id );
				if ( $attachment_url ) {
					$resolved_logo = $attachment_url;
				}
			}

			if ( '' === $resolved_logo && filter_var( $logo, FILTER_VALIDATE_URL ) ) {
				$resolved_logo = $logo;
			} elseif ( '' === $resolved_logo ) {
				$uploads = wp_upload_dir();
				if ( ! empty( $uploads['basedir'] ) && ! empty( $uploads['baseurl'] ) ) {
					$normalized_base = wp_normalize_path( $uploads['basedir'] );
					$normalized_logo = wp_normalize_path( $logo );
					if ( 0 === strpos( $normalized_logo, $normalized_base ) ) {
						$relative = ltrim( substr( $normalized_logo, strlen( $normalized_base ) ), '/\\' );
						$resolved_logo = trailingslashit( $uploads['baseurl'] ) . str_replace( '\\', '/', $relative );
					}
				}
			}
		}

		if ( '' === $resolved_logo ) {
			$uploads = wp_upload_dir();
			if ( ! empty( $uploads['basedir'] ) && ! empty( $uploads['baseurl'] ) ) {
				foreach ( array(
					'2026/04/Phoenix-Logo-PNG.png',
					'2026/04/Phoenix-Logo-PNG-1536x445.png',
					'2026/04/Phoenix-Logo-PNG-1024x296.png',
					'2026/04/Phoenix-Logo-PNG-768x222.png',
					'2026/04/Phoenix-Logo-PNG-300x87.png',
				) as $relative ) {
					$absolute = trailingslashit( $uploads['basedir'] ) . $relative;
					if ( file_exists( $absolute ) ) {
						$resolved_logo = trailingslashit( $uploads['baseurl'] ) . $relative;
						break;
					}
				}
			}
		}

		if ( '' !== $resolved_logo ) {
			set_transient( $cache_key, $resolved_logo, DAY_IN_SECONDS );
			return $resolved_logo;
		}

		$fallback = $this->fallback_logo_data_uri();
		set_transient( $cache_key, $fallback, HOUR_IN_SECONDS );
		return $fallback;
	}

	private function remote_image_to_data_uri( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 3,
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( 200 !== $code || '' === $body ) {
			return '';
		}

		$mime = (string) wp_remote_retrieve_header( $response, 'content-type' );
		if ( '' === $mime ) {
			$extension = strtolower( pathinfo( parse_url( $url, PHP_URL_PATH ) ?: '', PATHINFO_EXTENSION ) );
			$mime_map  = array( 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml' );
			$mime      = $mime_map[ $extension ] ?? 'image/png';
		} else {
			$parts = explode( ';', $mime );
			$mime  = trim( $parts[0] );
		}

		return 'data:' . $mime . ';base64,' . base64_encode( $body );
	}

	private function fallback_logo_data_uri() {
		$label = trim( (string) get_option( 'oksia_agency_name', 'OK' ) );
		if ( '' === $label ) {
			$label = 'OK';
		}

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="360" height="96" viewBox="0 0 360 96"><rect width="360" height="96" rx="10" fill="white"/><text x="18" y="42" font-family="Georgia, Times New Roman, serif" font-size="28" fill="#173f68">' . esc_html( $label ) . '</text><text x="18" y="70" font-family="Georgia, Times New Roman, serif" font-size="14" fill="#1f7a8c">Travel quotation</text></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	private function render_agent_workspace_markup( $post_id = 0 ) {
		if ( ! is_user_logged_in() ) {
		ob_start();
		?>
		<div class="oksia-intake-wrap">
			<div class="oksia-agent-login-card">
				<div class="oksia-agent-login-title"><?php esc_html_e( 'Agent login required', 'oksia-smart-itinerary-agent' ); ?></div>
					<div class="oksia-agent-login-text"><?php esc_html_e( 'Please log in to open the agent submission workspace.', 'oksia-smart-itinerary-agent' ); ?></div>
					<?php $this->render_agency_login_form( $this->current_url() ); ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		$today = wp_date( 'Y-m-d', current_time( 'timestamp' ) );
		$trip_option_sets = array(
			'hotel_categories' => $this->get_trip_type_setting_options(
				'oksia_hotel_categories_domestic',
				'oksia_hotel_categories_international',
				array( '3 Star', '4 Star', '5 Star', '3/4 Split', '3/5 Split', '4/5 Split' )
			),
			'occupancies' => $this->get_trip_type_setting_options(
				'oksia_occupancies_domestic',
				'oksia_occupancies_international',
				array( 'Single', 'Double', 'Triple', 'Quad' )
			),
			'meal_plans' => $this->get_trip_type_setting_options(
				'oksia_meal_plans_domestic',
				'oksia_meal_plans_international',
				array( 'No Meals', 'Breakfast', 'Breakfast & Dinner', 'Breakfast/Lunch/Dinner', 'Breakfast/Lunch/HiTea/Dinner' )
			),
			'pickup_points' => $this->get_trip_type_setting_options(
				'oksia_pickup_points_domestic',
				'oksia_pickup_points_international',
				array()
			),
			'drop_points' => $this->get_trip_type_setting_options(
				'oksia_drop_points_domestic',
				'oksia_drop_points_international',
				array()
			),
			'transfer_modes' => $this->get_trip_type_setting_options(
				'oksia_transfer_modes_domestic',
				'oksia_transfer_modes_international',
				array( 'Private', 'SIC - Sharing in Coach' )
			),
			'sightseeing_vehicles' => $this->get_trip_type_setting_options(
				'oksia_sightseeing_vehicles_domestic',
				'oksia_sightseeing_vehicles_international',
				array( 'Private', 'SIC - Sharing in Coach' )
			),
			'vehicle_types' => $this->get_trip_type_setting_options(
				'oksia_vehicle_types_domestic',
				'oksia_vehicle_types_international',
				array( 'Tempo Traveller', 'Innova', 'Sedan', 'SUV', 'Coach', 'Minibus' )
			),
		);
		$currencies = $this->get_setting_options( 'oksia_supported_currencies', array( 'INR', 'USD', 'EUR', 'AED', 'THB' ) );
		$vendor_modes = array(
			'single' => __( 'Single Vendor', 'oksia-smart-itinerary-agent' ),
			'multi'  => __( 'Multi Vendor', 'oksia-smart-itinerary-agent' ),
		);
		$travel_modes = array( 'Flight', 'Railway', 'Bus' );
		$multi_rate_components = array(
			'flight' => __( 'Flights', 'oksia-smart-itinerary-agent' ),
			'hotel' => __( 'Hotels', 'oksia-smart-itinerary-agent' ),
			'transportation' => __( 'Transportation', 'oksia-smart-itinerary-agent' ),
			'visa' => __( 'Visa', 'oksia-smart-itinerary-agent' ),
			'tourism_tax' => __( 'Tourism Tax', 'oksia-smart-itinerary-agent' ),
			'tip' => __( 'Tip', 'oksia-smart-itinerary-agent' ),
		);
		$country_codes = array( '+91', '+1', '+44', '+61', '+971', '+65', '+66', '+60' );
		$quote = array(
			'vendor_mode' => 'multi',
			'travel_mode' => 'Flight',
			'currency' => 'INR',
			'multi_currency' => 'INR',
			'exchange_rate' => '',
			'transaction_cost' => '1.9',
			'additional_cost' => '0',
			'effective_rate' => '',
			'adult_rate' => '',
			'with_bed_rate' => '',
			'child_rate' => '',
			'adult_markup' => '',
			'with_bed_markup' => '',
			'child_markup' => '',
			'single_txn_adult' => '',
			'single_txn_with_bed' => '',
			'single_txn_child' => '',
			'multi_flight_adult' => '',
			'multi_flight_with_bed' => '',
			'multi_flight_child' => '',
			'multi_hotel_adult' => '',
			'multi_hotel_with_bed' => '',
			'multi_hotel_child' => '',
			'multi_transportation_adult' => '',
			'multi_transportation_with_bed' => '',
			'multi_transportation_child' => '',
			'multi_visa_adult' => '',
			'multi_visa_with_bed' => '',
			'multi_visa_child' => '',
			'multi_tourism_tax_adult' => '',
			'multi_tourism_tax_with_bed' => '',
			'multi_tourism_tax_child' => '',
			'multi_tip_adult' => '',
			'multi_tip_with_bed' => '',
			'multi_tip_child' => '',
			'multi_adult_markup' => '',
			'multi_with_bed_markup' => '',
			'multi_child_markup' => '',
			'multi_txn_adult' => '',
			'multi_txn_with_bed' => '',
			'multi_txn_child' => '',
			'multi_adult_final' => '',
			'multi_with_bed_final' => '',
			'multi_child_final' => '',
			'multi_adult_rate_quote' => '',
			'multi_with_bed_rate_quote' => '',
			'multi_child_rate_quote' => '',
		);
		$editing_post_id = absint( $post_id );
		$existing_trip = $editing_post_id ? (array) get_post_meta( $editing_post_id, '_oksia_trip_overview', true ) : array();
		$existing_quote = $editing_post_id ? (array) get_post_meta( $editing_post_id, '_oksia_quote_details', true ) : array();
		$existing_hotel_plan = $editing_post_id ? (array) get_post_meta( $editing_post_id, '_oksia_hotel_plan', true ) : array();
		$existing_client_phone = $editing_post_id ? trim( (string) get_post_meta( $editing_post_id, '_oksia_client_phone', true ) ) : '';
		$existing_client_email = $editing_post_id ? trim( (string) get_post_meta( $editing_post_id, '_oksia_client_email', true ) ) : '';
		$existing_country_code = '+91';
		$existing_phone_number = '';
		if ( '' !== $existing_client_phone ) {
			$normalized_phone = trim( preg_replace( '/\s+/', ' ', $existing_client_phone ) );
			$matched_code = '';
			foreach ( $country_codes as $country_code ) {
				if ( 0 === strpos( $normalized_phone, $country_code . ' ' ) || 0 === strpos( $normalized_phone, $country_code ) ) {
					if ( strlen( $country_code ) > strlen( $matched_code ) ) {
						$matched_code = $country_code;
					}
				}
			}
			if ( '' !== $matched_code ) {
				$existing_country_code = $matched_code;
				$existing_phone_number = trim( str_replace( array( $matched_code, ' ' ), '', $normalized_phone ) );
			} elseif ( preg_match( '/^(\\+\\d{1,4})\\s*(.*)$/', $normalized_phone, $matches ) ) {
				$existing_country_code = trim( $matches[1] );
				$existing_phone_number = trim( $matches[2] );
			} else {
				$existing_phone_number = trim( preg_replace( '/[^0-9]/', '', $normalized_phone ) );
			}
		}
		$trip_values = wp_parse_args( $existing_trip, array(
			'salutation' => 'Mr',
			'country_code' => $existing_country_code,
			'client_name' => '',
			'phone' => $existing_phone_number,
			'email' => $existing_client_email,
			'trip_type' => 'Domestic',
			'destination' => '',
			'start_date' => $today,
			'end_date' => '',
			'total_nights' => '',
			'adults' => '1',
			'adult_with_bed' => '0',
			'child_without_bed' => '0',
			'travelers' => '1',
		) );
		$trip_type = 'International' === ( $trip_values['trip_type'] ?? 'Domestic' ) ? 'International' : 'Domestic';
		$hotel_categories = $trip_option_sets['hotel_categories'][ $trip_type ] ?? $trip_option_sets['hotel_categories']['Domestic'];
		$occupancies = $trip_option_sets['occupancies'][ $trip_type ] ?? $trip_option_sets['occupancies']['Domestic'];
		$meal_plans = $trip_option_sets['meal_plans'][ $trip_type ] ?? $trip_option_sets['meal_plans']['Domestic'];
		$pickup_points = $trip_option_sets['pickup_points'][ $trip_type ] ?? $trip_option_sets['pickup_points']['Domestic'];
		$drop_points = $trip_option_sets['drop_points'][ $trip_type ] ?? $trip_option_sets['drop_points']['Domestic'];
		$transfer_modes = $trip_option_sets['transfer_modes'][ $trip_type ] ?? $trip_option_sets['transfer_modes']['Domestic'];
		$sightseeing_vehicles = $trip_option_sets['sightseeing_vehicles'][ $trip_type ] ?? $trip_option_sets['sightseeing_vehicles']['Domestic'];
		$vehicle_types = $trip_option_sets['vehicle_types'][ $trip_type ] ?? $trip_option_sets['vehicle_types']['Domestic'];
		$meal_transfers = $this->get_setting_options( 'oksia_meal_transfer_types', array( 'Included', 'Excluded' ) );
		$quote = wp_parse_args( $existing_quote, $quote );
		$hotel_plan_rows = ! empty( $existing_hotel_plan ) ? array_values( $existing_hotel_plan ) : array(
			array(
				'city' => '',
				'hotel' => '',
				'nights' => '',
			),
		);
		$source_brief = $editing_post_id ? (string) get_post_meta( $editing_post_id, '_oksia_source_brief', true ) : '';
		$existing_operational = $editing_post_id ? (array) get_post_meta( $editing_post_id, '_oksia_operational_notes', true ) : array();
		$existing_days = $editing_post_id ? (array) get_post_meta( $editing_post_id, '_oksia_days', true ) : array();
		$brief_seed_lines = array();
		if ( '' !== trim( $source_brief ) ) {
			$brief_seed_lines = array_values(
				array_filter(
					array_map(
						'trim',
						preg_split( '/\r\n|\r|\n/', $source_brief )
					)
				)
			);
		}
		$brief_day_count = max( 1, absint( $trip_values['total_nights'] ) + 1, count( $brief_seed_lines ) );
		$brief_rows = array();
		for ( $i = 0; $i < $brief_day_count; $i++ ) {
			$line = trim( (string) ( $brief_seed_lines[ $i ] ?? '' ) );
			$line = preg_replace( '/^(?:\s*Day\s*\d+\s*:\s*)+/i', '', $line );
			$brief_rows[] = trim( (string) $line );
		}
		$operational_values = wp_parse_args( $existing_operational, array(
			'summary' => '',
			'inclusions' => '',
			'exclusions' => '',
			'important_notes' => '',
			'child_policy' => '',
			'booking_policy' => '',
			'cancellation_policy' => '',
		) );
		$day_rows = ! empty( $existing_days ) ? array_values( $existing_days ) : array(
			array(
				'title' => '',
				'location' => '',
				'description' => '',
				'logistics' => '',
				'image_id' => 0,
				'image_url' => '',
			),
		);
		$version_label = 'v1';
		$last_updated_by = __( 'System', 'oksia-smart-itinerary-agent' );
		if ( $editing_post_id && class_exists( 'OKSIA_Admin' ) ) {
			$version_label = OKSIA_Admin::get_quote_version_label( $editing_post_id );
			$last_updated_by = OKSIA_Admin::get_quote_last_updated_by_name( $editing_post_id );
		}
		$agent_error = isset( $_GET['oksia_error'] ) ? sanitize_text_field( wp_unslash( $_GET['oksia_error'] ) ) : '';
		$agent_error_fields = isset( $_GET['oksia_error_fields'] ) ? array_filter( array_map( 'sanitize_key', explode( ',', wp_unslash( $_GET['oksia_error_fields'] ) ) ) ) : array();
		$agent_error_labels = array();
		$agent_error_selectors = array();
		foreach ( $agent_error_fields as $field_key ) {
			$selector = '';
			$label = '';
			switch ( $field_key ) {
				case 'client_name':
					$selector = '#oksia_client_name';
					$label = __( 'Client Name', 'oksia-smart-itinerary-agent' );
					break;
				case 'phone':
					$selector = '#oksia_client_phone';
					$label = __( 'Phone', 'oksia-smart-itinerary-agent' );
					break;
				case 'email':
					$selector = '#oksia_client_email';
					$label = __( 'Email', 'oksia-smart-itinerary-agent' );
					break;
				case 'destination':
					$selector = '#oksia_destination_field';
					$label = __( 'Destination', 'oksia-smart-itinerary-agent' );
					break;
				case 'start_date':
					$selector = '#oksia_start_date';
					$label = __( 'Check-in', 'oksia-smart-itinerary-agent' );
					break;
				case 'end_date':
					$selector = '#oksia_end_date';
					$label = __( 'Check-out', 'oksia-smart-itinerary-agent' );
					break;
				case 'hotel_nights':
					$selector = '#oksia-hotel-plan';
					$label = __( 'Hotel Stay Nights', 'oksia-smart-itinerary-agent' );
					break;
			}
			if ( '' !== $selector ) {
				$agent_error_selectors[] = $selector;
			}
			if ( '' !== $label ) {
				$agent_error_labels[] = $label;
			}
		}
		ob_start();
		?>
		<div class="oksia-intake-wrap oksia-agent-workspace" style="--oksia-intake-primary:#173f68; --oksia-intake-primary-dark:#132041; --oksia-intake-primary-bg:#eef5fb; --oksia-intake-primary-text:#173f68; --oksia-intake-accent:#1f7a8c; --oksia-intake-gold:#4aa39b;">
			<?php if ( ! empty( $agent_error_fields ) ) : ?>
				<style>
					<?php foreach ( $agent_error_fields as $field_key ) : ?>
						<?php
						$selector = '';
						switch ( $field_key ) {
							case 'client_name':
								$selector = '#oksia_client_name';
								break;
							case 'phone':
								$selector = '#oksia_client_phone';
								break;
							case 'email':
								$selector = '#oksia_client_email';
								break;
							case 'destination':
								$selector = '#oksia_destination_field';
								break;
							case 'start_date':
								$selector = '#oksia_start_date';
								break;
							case 'end_date':
								$selector = '#oksia_end_date';
								break;
							case 'hotel_nights':
								$selector = '#oksia-hotel-plan';
								break;
						}
						if ( '' === $selector ) {
							continue;
						}
						?>
						<?php echo esc_html( $selector ); ?>{
							border-color:#d63638 !important;
							box-shadow:0 0 0 1px #d63638 inset, 0 0 0 3px rgba(214,54,56,.12) !important;
							background-color:#fff5f5 !important;
						}
						<?php echo esc_html( $selector ); ?>:focus{
							border-color:#d63638 !important;
							box-shadow:0 0 0 1px #d63638 inset, 0 0 0 3px rgba(214,54,56,.18) !important;
						}
					<?php endforeach; ?>
				</style>
			<?php endif; ?>
			<?php if ( '' !== $agent_error ) : ?>
				<div class="oksia-intake-err" style="display:block;margin-bottom:16px;"><?php echo esc_html( $agent_error ); ?></div>
				<?php if ( ! empty( $agent_error_labels ) ) : ?>
					<div class="oksia-intake-error-details" style="margin:0 0 18px;color:#7f1d1d;font-size:13px;line-height:1.5;">
						<strong><?php esc_html_e( 'Missing fields:', 'oksia-smart-itinerary-agent' ); ?></strong>
						<?php echo esc_html( implode( ', ', $agent_error_labels ) ); ?>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $agent_error_selectors ) ) : ?>
					<script>
						window.addEventListener('load', function () {
							var first = document.querySelector(<?php echo wp_json_encode( $agent_error_selectors[0] ); ?>);
							if (first && first.scrollIntoView) {
								first.scrollIntoView({behavior: 'smooth', block: 'center'});
							}
							if (first && first.focus) {
								first.focus({preventScroll: true});
							}
						});
					</script>
				<?php endif; ?>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="oksia-intake-card">
				<input type="hidden" name="action" value="oksia_submit_agent_workspace" />
				<?php wp_nonce_field( 'oksia_submit_agent_workspace', 'oksia_agent_nonce' ); ?>
				<input type="hidden" name="oksia_submission_id" value="<?php echo esc_attr( $editing_post_id ); ?>" />

				<script type="application/json" id="oksia-agent-rate-data"><?php echo wp_json_encode( $quote ); ?></script>

				<div class="oksia-agent-trip-details">
				<div class="oksia-intake-section-label"><?php esc_html_e( 'Trip Details', 'oksia-smart-itinerary-agent' ); ?></div>
				<div class="oksia-grid oksia-agent-trip-row oksia-agent-trip-row--top">
					<p>
						<label for="oksia_salutation"><?php esc_html_e( 'Salutation', 'oksia-smart-itinerary-agent' ); ?></label>
						<select id="oksia_salutation" name="oksia_trip[salutation]" class="widefat">
							<option value="Mr" <?php selected( $trip_values['salutation'], 'Mr' ); ?>>Mr</option>
							<option value="Ms" <?php selected( $trip_values['salutation'], 'Ms' ); ?>>Ms</option>
						</select>
					</p>
					<p>
						<label for="oksia_client_name"><?php esc_html_e( 'Client Name', 'oksia-smart-itinerary-agent' ); ?></label>
						<input type="text" id="oksia_client_name" name="oksia_trip[client_name]" value="<?php echo esc_attr( $trip_values['client_name'] ); ?>" class="widefat" />
					</p>
					<p>
						<label for="oksia_client_country_code"><?php esc_html_e( 'Country Code', 'oksia-smart-itinerary-agent' ); ?></label>
						<select id="oksia_client_country_code" name="oksia_trip[country_code]" class="widefat">
							<?php foreach ( $country_codes as $country_code ) : ?>
								<option value="<?php echo esc_attr( $country_code ); ?>" <?php selected( $country_code, $trip_values['country_code'] ); ?>><?php echo esc_html( $country_code ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="oksia_client_phone"><?php esc_html_e( 'Phone', 'oksia-smart-itinerary-agent' ); ?></label>
						<input type="tel" id="oksia_client_phone" name="oksia_trip[phone]" value="<?php echo esc_attr( $trip_values['phone'] ); ?>" placeholder="9876543210" class="widefat" />
					</p>
					<p>
						<label for="oksia_client_email"><?php esc_html_e( 'Email', 'oksia-smart-itinerary-agent' ); ?></label>
						<input type="email" id="oksia_client_email" name="oksia_trip[email]" value="<?php echo esc_attr( $trip_values['email'] ); ?>" placeholder="you@email.com" class="widefat" />
					</p>
					<p>
						<label for="oksia_trip_type"><?php esc_html_e( 'Trip Type', 'oksia-smart-itinerary-agent' ); ?></label>
						<select id="oksia_trip_type" name="oksia_trip[trip_type]" class="widefat">
							<option value="Domestic" <?php selected( $trip_values['trip_type'], 'Domestic' ); ?>>Domestic</option>
							<option value="International" <?php selected( $trip_values['trip_type'], 'International' ); ?>>International</option>
						</select>
					</p>
					<p>
						<label for="oksia_destination_field"><?php esc_html_e( 'Destination', 'oksia-smart-itinerary-agent' ); ?></label>
						<select id="oksia_destination_field" name="oksia_trip[destination]" class="widefat">
							<option value=""><?php esc_html_e( 'Select Destination', 'oksia-smart-itinerary-agent' ); ?></option>
							<?php if ( '' !== $trip_values['destination'] ) : ?>
								<option value="<?php echo esc_attr( $trip_values['destination'] ); ?>" selected><?php echo esc_html( $trip_values['destination'] ); ?></option>
							<?php endif; ?>
						</select>
					</p>
				</div>
				<div class="oksia-grid oksia-agent-trip-row oksia-agent-trip-row--dates">
					<p>
						<label for="oksia_start_date"><?php esc_html_e( 'Check-in', 'oksia-smart-itinerary-agent' ); ?></label>
						<input type="date" id="oksia_start_date" name="oksia_trip[start_date]" value="<?php echo esc_attr( $trip_values['start_date'] ); ?>" min="<?php echo esc_attr( $today ); ?>" class="widefat" />
					</p>
					<p>
						<label for="oksia_end_date"><?php esc_html_e( 'Check-out', 'oksia-smart-itinerary-agent' ); ?></label>
						<input type="date" id="oksia_end_date" name="oksia_trip[end_date]" value="<?php echo esc_attr( $trip_values['end_date'] ); ?>" min="<?php echo esc_attr( $today ); ?>" class="widefat" />
					</p>
					<p>
						<label for="oksia_total_nights"><?php esc_html_e( 'Total Nights', 'oksia-smart-itinerary-agent' ); ?></label>
						<input type="number" id="oksia_total_nights" name="oksia_trip[total_nights]" value="<?php echo esc_attr( $trip_values['total_nights'] ); ?>" class="widefat" readonly />
					</p>
					<p><label for="oksia_adults"><?php esc_html_e( 'Adults', 'oksia-smart-itinerary-agent' ); ?></label><input type="number" min="0" id="oksia_adults" name="oksia_trip[adults]" value="<?php echo esc_attr( $trip_values['adults'] ); ?>" class="widefat" /></p>
					<p><label for="oksia_adult_with_bed"><?php esc_html_e( 'Adult/Child With Bed', 'oksia-smart-itinerary-agent' ); ?></label><input type="number" min="0" id="oksia_adult_with_bed" name="oksia_trip[adult_with_bed]" value="<?php echo esc_attr( $trip_values['adult_with_bed'] ); ?>" class="widefat" /></p>
					<p><label for="oksia_child_without_bed"><?php esc_html_e( 'Child Without Bed', 'oksia-smart-itinerary-agent' ); ?></label><input type="number" min="0" id="oksia_child_without_bed" name="oksia_trip[child_without_bed]" value="<?php echo esc_attr( $trip_values['child_without_bed'] ); ?>" class="widefat" /></p>
					<p><label for="oksia_total_travelers"><?php esc_html_e( 'Total Travelers', 'oksia-smart-itinerary-agent' ); ?></label><input type="number" id="oksia_total_travelers" name="oksia_trip[travelers]" value="<?php echo esc_attr( $trip_values['travelers'] ); ?>" class="widefat" readonly /></p>
				</div>
				</div>
				<div class="oksia-agent-columns oksia-agent-columns--right">
				<div class="oksia-intake-divider"></div>
				<div class="oksia-intake-section-label"><?php esc_html_e( 'Stay Plan', 'oksia-smart-itinerary-agent' ); ?></div>
				<div id="oksia-hotel-plan">
					<?php foreach ( $hotel_plan_rows as $index => $stay ) : ?>
						<div class="oksia-hotel-plan-row">
							<p><label><?php esc_html_e( 'City', 'oksia-smart-itinerary-agent' ); ?></label><input type="text" name="oksia_hotel_plan[<?php echo esc_attr( $index ); ?>][city]" value="<?php echo esc_attr( $stay['city'] ?? '' ); ?>" class="widefat" /></p>
							<p><label><?php esc_html_e( 'Hotel Name', 'oksia-smart-itinerary-agent' ); ?></label><input type="text" name="oksia_hotel_plan[<?php echo esc_attr( $index ); ?>][hotel]" value="<?php echo esc_attr( $stay['hotel'] ?? '' ); ?>" class="widefat" /></p>
							<p><label><?php esc_html_e( 'Night', 'oksia-smart-itinerary-agent' ); ?></label><input type="number" min="0" name="oksia_hotel_plan[<?php echo esc_attr( $index ); ?>][nights]" value="<?php echo esc_attr( $stay['nights'] ?? '' ); ?>" class="widefat" /></p>
							<p class="oksia-row-action oksia-row-action--add">
								<?php if ( 0 === (int) $index ) : ?>
									<button type="button" class="oksia-icon-action oksia-icon-action--add" id="oksia-add-hotel-plan" aria-label="<?php esc_attr_e( 'Add stay stop', 'oksia-smart-itinerary-agent' ); ?>" title="<?php esc_attr_e( 'Add stay stop', 'oksia-smart-itinerary-agent' ); ?>"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Add Stay Stop', 'oksia-smart-itinerary-agent' ); ?></span></button>
								<?php endif; ?>
							</p>
							<p class="oksia-row-action oksia-row-action--remove">
								<button type="button" class="oksia-icon-action oksia-icon-action--remove oksia-remove-row" aria-label="<?php esc_attr_e( 'Remove stop', 'oksia-smart-itinerary-agent' ); ?>" title="<?php esc_attr_e( 'Remove stop', 'oksia-smart-itinerary-agent' ); ?>"><span class="dashicons dashicons-minus" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Remove stop', 'oksia-smart-itinerary-agent' ); ?></span></button>
							</p>
						</div>
					<?php endforeach; ?>
				</div>
				<p id="oksia-hotel-night-check" class="oksia-hotel-night-check" aria-live="polite"></p>
				<script type="text/html" id="tmpl-oksia-hotel-plan-row">
					<div class="oksia-hotel-plan-row">
						<p><label><?php esc_html_e( 'City', 'oksia-smart-itinerary-agent' ); ?></label><input type="text" name="oksia_hotel_plan[__INDEX__][city]" class="widefat" /></p>
						<p><label><?php esc_html_e( 'Hotel Name', 'oksia-smart-itinerary-agent' ); ?></label><input type="text" name="oksia_hotel_plan[__INDEX__][hotel]" class="widefat" /></p>
						<p><label><?php esc_html_e( 'Night', 'oksia-smart-itinerary-agent' ); ?></label><input type="number" min="0" name="oksia_hotel_plan[__INDEX__][nights]" class="widefat" /></p>
						<p class="oksia-row-action oksia-row-action--add"></p>
						<p class="oksia-row-action oksia-row-action--remove"><button type="button" class="oksia-icon-action oksia-icon-action--remove oksia-remove-row" aria-label="<?php esc_attr_e( 'Remove stop', 'oksia-smart-itinerary-agent' ); ?>" title="<?php esc_attr_e( 'Remove stop', 'oksia-smart-itinerary-agent' ); ?>"><span class="dashicons dashicons-minus" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Remove stop', 'oksia-smart-itinerary-agent' ); ?></span></button></p>
					</div>
				</script>

				<div class="oksia-intake-section-label"><?php esc_html_e( 'Trip Brief For AI', 'oksia-smart-itinerary-agent' ); ?></div>
				<div class="oksia-agent-trip-brief-panel__header">
					<div>
						<p class="oksia-agent-trip-brief-panel__note"><?php esc_html_e( 'Add one line per trip day. Leave a row blank to mark it as a Leisure Day.', 'oksia-smart-itinerary-agent' ); ?></p>
					</div>
					<div class="oksia-agent-trip-brief-panel__meta">
						<span class="oksia-agent-brief-count"><?php echo esc_html( sprintf( _n( '%s day row', '%s day rows', $brief_day_count, 'oksia-smart-itinerary-agent' ), number_format_i18n( $brief_day_count ) ) ); ?></span>
					</div>
				</div>
				<div class="oksia-agent-brief-days" id="oksia-agent-brief-days" data-brief-day-count="<?php echo esc_attr( (string) $brief_day_count ); ?>">
					<?php foreach ( $brief_rows as $index => $brief_row ) : ?>
						<div class="oksia-agent-brief-day-row<?php echo '' === trim( $brief_row ) ? ' oksia-agent-brief-day-row--leisure' : ''; ?>" data-day-number="<?php echo esc_attr( (string) ( $index + 1 ) ); ?>">
							<div class="oksia-agent-brief-day-row__top">
								<span class="oksia-agent-brief-day-badge"><?php echo esc_html( sprintf( __( 'Day %d', 'oksia-smart-itinerary-agent' ), $index + 1 ) ); ?></span>
								<span class="oksia-agent-brief-day-state"><?php echo esc_html( '' !== trim( $brief_row ) ? __( 'Planned day', 'oksia-smart-itinerary-agent' ) : __( 'Leisure Day', 'oksia-smart-itinerary-agent' ) ); ?></span>
							</div>
							<input type="text" class="widefat oksia-agent-brief-day-input" value="<?php echo esc_attr( $brief_row ); ?>" placeholder="<?php esc_attr_e( 'Arrival, sightseeing, dinner, etc.', 'oksia-smart-itinerary-agent' ); ?>" />
						</div>
					<?php endforeach; ?>
				</div>
				<textarea id="oksia_source_brief" name="oksia_source_brief" class="oksia-agent-brief-source" aria-hidden="true"><?php echo esc_textarea( $source_brief ); ?></textarea>

				</div>
				<div class="oksia-agent-columns oksia-agent-columns--left">
				<div class="oksia-intake-divider"></div>
				<div class="oksia-intake-section-label"><?php esc_html_e( 'Hotels & Meals', 'oksia-smart-itinerary-agent' ); ?></div>
				<div class="oksia-grid oksia-agent-hotels-row">
					<p><label for="oksia_hotel_category"><?php esc_html_e( 'Hotel Category', 'oksia-smart-itinerary-agent' ); ?></label><select id="oksia_hotel_category" name="oksia_quote[hotel_category]" class="widefat"><?php foreach ( $hotel_categories as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $quote['hotel_category'] ?? '', $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></p>
					<p><label for="oksia_occupancy"><?php esc_html_e( 'Occupancy', 'oksia-smart-itinerary-agent' ); ?></label><select id="oksia_occupancy" name="oksia_quote[occupancy]" class="widefat"><?php foreach ( $occupancies as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $quote['occupancy'] ?? '', $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></p>
					<p><label for="oksia_rooms"><?php esc_html_e( 'No. of Rooms', 'oksia-smart-itinerary-agent' ); ?></label><input type="number" min="0" id="oksia_rooms" name="oksia_quote[rooms]" value="<?php echo esc_attr( $quote['rooms'] ?? 0 ); ?>" class="widefat" /></p>
					<p><label for="oksia_meal_plan"><?php esc_html_e( 'Meal Plan', 'oksia-smart-itinerary-agent' ); ?></label><select id="oksia_meal_plan" name="oksia_quote[meal_plan]" class="widefat"><?php foreach ( $meal_plans as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $quote['meal_plan'] ?? '', $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></p>
					<p class="oksia-conditional-field" data-show-trip-type="International"><label for="oksia_meal_transfers"><?php esc_html_e( 'Meal Transfers', 'oksia-smart-itinerary-agent' ); ?></label><select id="oksia_meal_transfers" name="oksia_quote[meal_transfers]" class="widefat"><?php foreach ( $meal_transfers as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $quote['meal_transfers'] ?? '', $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></p>
				</div>

				<div class="oksia-intake-divider"></div>
				<div class="oksia-intake-section-label"><?php esc_html_e( 'Transfers', 'oksia-smart-itinerary-agent' ); ?></div>
				<div class="oksia-grid oksia-agent-transfers-row">
					<p><label for="oksia_pickup_from"><?php esc_html_e( 'Pick Up From', 'oksia-smart-itinerary-agent' ); ?></label><select id="oksia_pickup_from" name="oksia_quote[pickup_from]" class="widefat"><?php foreach ( $pickup_points as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $quote['pickup_from'] ?? '', $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></p>
					<p><label for="oksia_drop_to"><?php esc_html_e( 'Drop To', 'oksia-smart-itinerary-agent' ); ?></label><select id="oksia_drop_to" name="oksia_quote[drop_to]" class="widefat"><?php foreach ( $drop_points as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $quote['drop_to'] ?? '', $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></p>
					<p><label for="oksia_first_transfer"><?php esc_html_e( 'First Transfer', 'oksia-smart-itinerary-agent' ); ?></label><select id="oksia_first_transfer" name="oksia_quote[first_transfer]" class="widefat"><?php foreach ( $transfer_modes as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $quote['first_transfer'] ?? '', $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></p>
					<p><label for="oksia_last_transfer"><?php esc_html_e( 'Last Transfer', 'oksia-smart-itinerary-agent' ); ?></label><select id="oksia_last_transfer" name="oksia_quote[last_transfer]" class="widefat"><?php foreach ( $transfer_modes as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $quote['last_transfer'] ?? '', $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></p>
					<p><label for="oksia_sightseeing_vehicle"><?php esc_html_e( 'Sightseeing Vehicle', 'oksia-smart-itinerary-agent' ); ?></label><select id="oksia_sightseeing_vehicle" name="oksia_quote[sightseeing_vehicle]" class="widefat"><?php foreach ( $sightseeing_vehicles as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $quote['sightseeing_vehicle'] ?? '', $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></p>
				</div>
				<div class="oksia-grid oksia-agent-transfer-meta-row">
					<p><label for="oksia_vehicle_type"><?php esc_html_e( 'Vehicle Type', 'oksia-smart-itinerary-agent' ); ?></label><select id="oksia_vehicle_type" name="oksia_quote[vehicle_type]" class="widefat"><?php foreach ( $vehicle_types as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $quote['vehicle_type'] ?? '', $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></p>
					<p class="oksia-agent-transfer-note"><label for="oksia_transfer_note"><?php esc_html_e( 'Transfer Note', 'oksia-smart-itinerary-agent' ); ?></label><input type="text" id="oksia_transfer_note" name="oksia_quote[transfer_note]" value="<?php echo esc_attr( $quote['transfer_note'] ?? '' ); ?>" class="widefat" /></p>
				</div>

				<div class="oksia-intake-divider"></div>
				<div class="oksia-intake-section-label"><?php esc_html_e( 'Rates', 'oksia-smart-itinerary-agent' ); ?></div>
				<div class="oksia-grid oksia-grid--five">
					<p><label for="oksia_currency"><?php esc_html_e( 'Quote Currency', 'oksia-smart-itinerary-agent' ); ?></label><select id="oksia_currency" name="oksia_quote[currency]" class="widefat"><?php foreach ( $currencies as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $quote['currency'], $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></p>
					<p><label for="oksia_exchange_rate"><?php esc_html_e( 'Exchange Rate (to INR)', 'oksia-smart-itinerary-agent' ); ?></label><input type="text" id="oksia_exchange_rate" name="oksia_quote[exchange_rate]" value="<?php echo esc_attr( $quote['exchange_rate'] ); ?>" class="widefat" readonly /></p>
					<p><label for="oksia_transaction_cost"><?php esc_html_e( 'Transaction Cost (INR)', 'oksia-smart-itinerary-agent' ); ?></label><input type="number" step="0.01" id="oksia_transaction_cost" name="oksia_quote[transaction_cost]" value="<?php echo esc_attr( $quote['transaction_cost'] ); ?>" class="widefat" /></p>
					<p><label for="oksia_additional_cost"><?php esc_html_e( 'Additional Cost (INR)', 'oksia-smart-itinerary-agent' ); ?></label><input type="number" step="0.01" id="oksia_additional_cost" name="oksia_quote[additional_cost]" value="<?php echo esc_attr( $quote['additional_cost'] ); ?>" class="widefat" /></p>
					<p><label for="oksia_effective_rate"><?php esc_html_e( 'Effective Rate', 'oksia-smart-itinerary-agent' ); ?></label><input type="text" id="oksia_effective_rate" name="oksia_quote[effective_rate]" value="<?php echo esc_attr( $quote['effective_rate'] ); ?>" class="widefat" readonly /></p>
				</div>
				<div class="oksia-grid oksia-grid--three">
					<p><label for="oksia_adult_rate"><?php esc_html_e( 'Adult Rate', 'oksia-smart-itinerary-agent' ); ?> (<span class="oksia-selected-currency-label"><?php echo esc_html( 'INR' ); ?></span>)</label><input type="number" step="0.01" id="oksia_adult_rate" name="oksia_quote[adult_rate]" value="<?php echo esc_attr( $quote['adult_rate'] ); ?>" class="widefat" /></p>
					<p><label for="oksia_with_bed_rate"><?php esc_html_e( 'Adult/Child With Bed Rate', 'oksia-smart-itinerary-agent' ); ?> (<span class="oksia-selected-currency-label"><?php echo esc_html( 'INR' ); ?></span>)</label><input type="number" step="0.01" id="oksia_with_bed_rate" name="oksia_quote[with_bed_rate]" value="<?php echo esc_attr( $quote['with_bed_rate'] ); ?>" class="widefat" /></p>
					<p><label for="oksia_child_rate"><?php esc_html_e( 'Child Without Bed Rate', 'oksia-smart-itinerary-agent' ); ?> (<span class="oksia-selected-currency-label"><?php echo esc_html( 'INR' ); ?></span>)</label><input type="number" step="0.01" id="oksia_child_rate" name="oksia_quote[child_rate]" value="<?php echo esc_attr( $quote['child_rate'] ); ?>" class="widefat" /></p>
				</div>
				<div class="oksia-grid oksia-grid--three oksia-rate-markup-row">
					<p><label for="oksia_adult_markup"><?php esc_html_e( 'Adult Markup', 'oksia-smart-itinerary-agent' ); ?> (<span class="oksia-selected-currency-label"><?php echo esc_html( $quote['currency'] ); ?></span>)</label><input type="number" step="0.01" id="oksia_adult_markup" name="oksia_quote[adult_markup]" value="<?php echo esc_attr( $quote['adult_markup'] ); ?>" class="widefat" /></p>
					<p><label for="oksia_with_bed_markup"><?php esc_html_e( 'Extra / With Bed Markup', 'oksia-smart-itinerary-agent' ); ?> (<span class="oksia-selected-currency-label"><?php echo esc_html( $quote['currency'] ); ?></span>)</label><input type="number" step="0.01" id="oksia_with_bed_markup" name="oksia_quote[with_bed_markup]" value="<?php echo esc_attr( $quote['with_bed_markup'] ); ?>" class="widefat" /></p>
					<p><label for="oksia_child_markup"><?php esc_html_e( 'Child Markup', 'oksia-smart-itinerary-agent' ); ?> (<span class="oksia-selected-currency-label"><?php echo esc_html( $quote['currency'] ); ?></span>)</label><input type="number" step="0.01" id="oksia_child_markup" name="oksia_quote[child_markup]" value="<?php echo esc_attr( $quote['child_markup'] ); ?>" class="widefat" /></p>
				</div>
				<div class="oksia-grid oksia-grid--three">
					<p><label for="oksia_adult_rate_quote"><?php esc_html_e( 'Adult INR Reference Rate', 'oksia-smart-itinerary-agent' ); ?></label><input type="text" id="oksia_adult_rate_quote" name="oksia_quote[adult_rate_quote]" value="<?php echo esc_attr( $quote['adult_rate_quote'] ?? '' ); ?>" class="widefat" readonly /></p>
					<p><label for="oksia_with_bed_rate_quote"><?php esc_html_e( 'With Bed INR Reference Rate', 'oksia-smart-itinerary-agent' ); ?></label><input type="text" id="oksia_with_bed_rate_quote" name="oksia_quote[with_bed_rate_quote]" value="<?php echo esc_attr( $quote['with_bed_rate_quote'] ?? '' ); ?>" class="widefat" readonly /></p>
					<p><label for="oksia_child_rate_quote"><?php esc_html_e( 'Child INR Reference Rate', 'oksia-smart-itinerary-agent' ); ?></label><input type="text" id="oksia_child_rate_quote" name="oksia_quote[child_rate_quote]" value="<?php echo esc_attr( $quote['child_rate_quote'] ?? '' ); ?>" class="widefat" readonly /></p>
				</div>

				<div class="oksia-package-summary-block">
					<div class="oksia-intake-section-label"><?php esc_html_e( 'Package Summary', 'oksia-smart-itinerary-agent' ); ?></div>
					<div class="oksia-grid oksia-grid--two oksia-package-summary">
						<p>
							<label for="oksia_package_base_total"><?php esc_html_e( 'Package Cost Without Markup', 'oksia-smart-itinerary-agent' ); ?> (<span class="oksia-selected-currency-label"><?php echo esc_html( $quote['currency'] ); ?></span>)</label>
							<input type="text" id="oksia_package_base_total" name="oksia_quote[package_base_total]" value="<?php echo esc_attr( $quote['package_base_total'] ?? '' ); ?>" class="widefat" readonly />
						</p>
						<p>
							<label for="oksia_package_customer_total"><?php esc_html_e( 'Package Cost With Markup', 'oksia-smart-itinerary-agent' ); ?> (<span class="oksia-selected-currency-label"><?php echo esc_html( $quote['currency'] ); ?></span>)</label>
							<input type="text" id="oksia_package_customer_total" name="oksia_quote[package_customer_total]" value="<?php echo esc_attr( $quote['package_customer_total'] ?? '' ); ?>" class="widefat" readonly />
						</p>
					</div>
					<p class="oksia-package-summary-note"><?php esc_html_e( 'These totals are calculated automatically and stay locked for staff.', 'oksia-smart-itinerary-agent' ); ?></p>
				</div>
				</div>
				<p class="oksia-agent-submit-row"><button type="submit" class="oksia-intake-submit"><?php esc_html_e( 'Generate AI Itinerary', 'oksia-smart-itinerary-agent' ); ?></button></p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/* -------------------------------------------------------------------------
	 * AJAX handler
	 * ---------------------------------------------------------------------- */

	public function handle_submission() {
		check_ajax_referer( 'oksia_intake_nonce', 'nonce' );

		$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$salutation = sanitize_text_field( wp_unslash( $_POST['salutation'] ?? 'Mr' ) );
		$phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$type    = sanitize_text_field( wp_unslash( $_POST['trip_type'] ?? 'Domestic' ) );
		$dest    = sanitize_text_field( wp_unslash( $_POST['destination'] ?? '' ) );
		$start   = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
		$end     = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );
		$portal  = sanitize_text_field( wp_unslash( $_POST['portal_mode'] ?? 'public' ) );
		$nights  = absint( $_POST['nights'] ?? 0 );
		$adults  = absint( $_POST['adults'] ?? 1 );
		$c611    = absint( $_POST['children_611'] ?? 0 );
		$inf     = absint( $_POST['infants'] ?? 0 );

		// Validate
		$missing_fields = array();
		if ( ! $name ) {
			$missing_fields[] = 'client_name';
		}
		if ( strlen( $phone ) < 10 ) {
			$missing_fields[] = 'phone';
		}
		if ( ! is_email( $email ) ) {
			$missing_fields[] = 'email';
		}
		if ( ! $dest ) {
			$missing_fields[] = 'destination';
		}
		if ( ! $start ) {
			$missing_fields[] = 'start_date';
		}
		if ( ! $end ) {
			$missing_fields[] = 'end_date';
		}
		if ( ! empty( $missing_fields ) ) {
			$this->redirect_agent_workspace_with_error( __( 'Please complete the required trip fields.', 'oksia-smart-itinerary-agent' ), $missing_fields );
		}
		if ( $nights < 1 )                      wp_send_json_error( 'Please enter the number of nights.' );
		if ( $adults < 1 )                      wp_send_json_error( 'At least one adult is required.' );
		if ( 'agent' === $portal && ! is_user_logged_in() ) wp_send_json_error( 'Please log in to submit as an agent.' );

		// Create share-ready itinerary post (workflow stays controlled by quote stage meta).
		$post_title = $name . ' - ' . $dest;
		$post_author = 'agent' === $portal ? $this->get_main_agency_user_id() : get_current_user_id();
		$post_id    = wp_insert_post( array(
			'post_type'   => OKSIA_Post_Types::POST_TYPE,
			'post_title'  => $post_title,
			'post_status' => 'publish',
			'post_author' => $post_author,
		) );

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( 'Could not create itinerary. Please try again.' );
		}

		// Generate and save Quote ID
		$quote_id = $this->generate_next_quote_id();
		update_post_meta( $post_id, '_oksia_quote_id', $quote_id );
		if ( class_exists( 'OKSIA_Agencies' ) ) {
			$agency_id = $this->get_current_agency_id();
			if ( $agency_id > 0 ) {
				update_post_meta( $post_id, '_oksia_agency_id', $agency_id );
				update_post_meta( $post_id, '_oksia_agency_code', OKSIA_Agencies::instance()->get_agency_code( $agency_id ) );
			}
		}

		// Save trip overview (matches existing plugin meta structure)
		update_post_meta( $post_id, '_oksia_trip_overview', array(
			'salutation'        => $salutation ? $salutation : 'Mr',
			'client_name'       => $name,
			'trip_type'         => $type,
			'destination'       => $dest,
			'start_date'        => $start,
			'end_date'          => $end,
			'total_nights'      => $nights,
			'adults'            => $adults,
			'adult_with_bed'    => $c611,
			'child_without_bed' => $inf,
			'travelers'         => $adults + $c611 + $inf,
		) );

		// Save client contact details
		update_post_meta( $post_id, '_oksia_client_phone', trim( $trip_clean['country_code'] . ' ' . $trip_clean['phone'] ) );
		update_post_meta( $post_id, '_oksia_client_email', $email );

		// Mark as intake-sourced so agent knows origin
		update_post_meta( $post_id, '_oksia_intake_source', 'agent' === $portal ? 'agent_portal' : 'public_form' );
		update_post_meta( $post_id, '_oksia_submitted_by_user', get_current_user_id() );
		update_post_meta( $post_id, '_oksia_ai_status', 'New intake - agent action required.' );

		// Notify agent by email
		$this->notify_agent( $post_id, $quote_id, $name, $phone, $email, $type, $dest, $start, $end, $nights, $adults, $c611, $inf );

		wp_send_json_success( array(
			'quote_id' => $quote_id,
			'salutation' => $salutation ? $salutation : 'Mr',
			'name'     => $name,
			'phone'    => '+91 ' . $phone,
			'email'    => $email,
			'type'     => $type,
			'dest'     => $dest,
			'start'    => $start,
			'end'      => $end,
			'nights'   => $nights,
			'adults'   => $adults,
			'c611'     => $c611,
			'inf'      => $inf,
		) );
	}

	public function handle_agent_submission() {
		if ( ! is_user_logged_in() ) {
			$this->redirect_agent_login();
		}

		if ( ! isset( $_POST['oksia_agent_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oksia_agent_nonce'] ) ), 'oksia_submit_agent_workspace' ) ) {
			$this->redirect_agent_workspace_with_error( __( 'Invalid request.', 'oksia-smart-itinerary-agent' ) );
		}

		$trip = isset( $_POST['oksia_trip'] ) ? (array) wp_unslash( $_POST['oksia_trip'] ) : array();
		$quote = isset( $_POST['oksia_quote'] ) ? (array) wp_unslash( $_POST['oksia_quote'] ) : array();
		$operational = isset( $_POST['oksia_operational'] ) ? (array) wp_unslash( $_POST['oksia_operational'] ) : array();
		$hotel_plan = isset( $_POST['oksia_hotel_plan'] ) ? (array) wp_unslash( $_POST['oksia_hotel_plan'] ) : array();
		$documents = isset( $_POST['oksia_documents'] ) ? (array) wp_unslash( $_POST['oksia_documents'] ) : array();
		$days = isset( $_POST['oksia_days'] ) ? (array) wp_unslash( $_POST['oksia_days'] ) : array();
		$submission_id = absint( $_POST['oksia_submission_id'] ?? 0 );
		$existing_trip = $submission_id ? (array) get_post_meta( $submission_id, '_oksia_trip_overview', true ) : array();
		$existing_quote = $submission_id ? (array) get_post_meta( $submission_id, '_oksia_quote_details', true ) : array();
		$existing_hotel_plan = $submission_id ? (array) get_post_meta( $submission_id, '_oksia_hotel_plan', true ) : array();
		$existing_documents = $submission_id ? (array) get_post_meta( $submission_id, '_oksia_documents', true ) : array();
		$existing_operational = $submission_id ? (array) get_post_meta( $submission_id, '_oksia_operational_notes', true ) : array();
		$existing_days = $submission_id ? (array) get_post_meta( $submission_id, '_oksia_days', true ) : array();

		$trip_clean = array(
			'salutation' => sanitize_text_field( $trip['salutation'] ?? ( $existing_trip['salutation'] ?? 'Mr' ) ),
			'country_code' => sanitize_text_field( $trip['country_code'] ?? ( $existing_trip['country_code'] ?? '+91' ) ),
			'client_name' => sanitize_text_field( $trip['client_name'] ?? ( $existing_trip['client_name'] ?? '' ) ),
			'phone' => sanitize_text_field( $trip['phone'] ?? ( $existing_trip['phone'] ?? '' ) ),
			'email' => sanitize_email( $trip['email'] ?? ( $existing_trip['email'] ?? '' ) ),
			'trip_type' => sanitize_text_field( $trip['trip_type'] ?? ( $existing_trip['trip_type'] ?? 'Domestic' ) ),
			'destination' => sanitize_text_field( $trip['destination'] ?? ( $existing_trip['destination'] ?? '' ) ),
			'start_date' => sanitize_text_field( $trip['start_date'] ?? ( $existing_trip['start_date'] ?? '' ) ),
			'end_date' => sanitize_text_field( $trip['end_date'] ?? ( $existing_trip['end_date'] ?? '' ) ),
			'total_nights' => $this->calculate_nights( $trip['start_date'] ?? ( $existing_trip['start_date'] ?? '' ), $trip['end_date'] ?? ( $existing_trip['end_date'] ?? '' ) ),
			'adults' => absint( $trip['adults'] ?? ( $existing_trip['adults'] ?? 0 ) ),
			'adult_with_bed' => absint( $trip['adult_with_bed'] ?? ( $existing_trip['adult_with_bed'] ?? 0 ) ),
			'child_without_bed' => absint( $trip['child_without_bed'] ?? ( $existing_trip['child_without_bed'] ?? 0 ) ),
			'travelers' => 0,
		);
		$trip_clean['travelers'] = $trip_clean['adults'] + $trip_clean['adult_with_bed'] + $trip_clean['child_without_bed'];

		if ( ! $trip_clean['client_name'] || ! $trip_clean['phone'] || ! $trip_clean['email'] || ! $trip_clean['destination'] || ! $trip_clean['start_date'] || ! $trip_clean['end_date'] ) {
			$this->redirect_agent_workspace_with_error( __( 'Please complete the required trip fields.', 'oksia-smart-itinerary-agent' ) );
		}

		$source_brief_clean = sanitize_textarea_field( wp_unslash( $_POST['oksia_source_brief'] ?? '' ) );
		$quote_clean = $this->sanitize_agent_quote( $quote, $trip_clean['trip_type'] );
		$hotel_clean = $this->sanitize_hotel_plan_data( $hotel_plan );
		$documents_clean = $this->sanitize_document_data( $documents );
		$operational_clean = $this->sanitize_operational_data( $operational );
		$days_clean = $this->sanitize_day_data( $days );
		$expected_hotel_nights = absint( $trip_clean['total_nights'] );
		$hotel_nights_total = $this->get_hotel_plan_nights_total( $hotel_clean );
		if ( ! empty( $hotel_clean ) && $expected_hotel_nights > 0 && $hotel_nights_total !== $expected_hotel_nights ) {
			$this->redirect_agent_workspace_with_error(
				sprintf(
					/* translators: 1: expected nights, 2: total nights entered in stay plan */
					__( 'Hotel stay nights must match total nights. Expected %1$d, found %2$d.', 'oksia-smart-itinerary-agent' ),
					$expected_hotel_nights,
					$hotel_nights_total
				),
				array( 'hotel_nights' )
			);
		}
		$ai_input_hash = $this->build_agent_ai_input_hash( $trip_clean, $source_brief_clean, $days_clean );
		$force_ai = ! empty( $_POST['oksia_force_ai'] );

		if ( $submission_id ) {
			$updated = wp_update_post(
				array(
					'ID' => $submission_id,
					'post_type' => OKSIA_Post_Types::POST_TYPE,
					'post_status' => get_post_status( $submission_id ) ?: 'publish',
					'post_title' => sprintf( '%s - %s', $trip_clean['client_name'], $trip_clean['destination'] ),
					'post_author' => (int) get_post_field( 'post_author', $submission_id ),
				),
				true
			);

			if ( is_wp_error( $updated ) ) {
				$this->redirect_agent_workspace_with_error( $updated->get_error_message() );
			}

			$post_id = (int) $updated;
		} else {
			$post_id = wp_insert_post(
				array(
					'post_type' => OKSIA_Post_Types::POST_TYPE,
					'post_status' => 'publish',
					'post_title' => sprintf( '%s - %s', $trip_clean['client_name'], $trip_clean['destination'] ),
					'post_author' => $this->get_main_agency_user_id(),
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				$this->redirect_agent_workspace_with_error( $post_id->get_error_message() );
			}
		}

		if ( ! get_post_meta( $post_id, '_oksia_quote_id', true ) ) {
			update_post_meta( $post_id, '_oksia_quote_id', $this->generate_next_quote_id() );
		}
		if ( class_exists( 'OKSIA_Agencies' ) ) {
			$agency_id = $this->get_current_agency_id();
			if ( $agency_id > 0 ) {
				update_post_meta( $post_id, '_oksia_agency_id', $agency_id );
				update_post_meta( $post_id, '_oksia_agency_code', OKSIA_Agencies::instance()->get_agency_code( $agency_id ) );
			}
		}

		if ( empty( $quote_clean['hotel_category'] ) && ! empty( $existing_quote ) ) {
			$quote_clean = array_merge( $existing_quote, $quote_clean );
		}
		if ( empty( $hotel_clean ) && ! empty( $existing_hotel_plan ) ) {
			$hotel_clean = $existing_hotel_plan;
		}
		if ( empty( $documents_clean ) && ! empty( $existing_documents ) ) {
			$documents_clean = $existing_documents;
		}
		if ( 0 === count( array_filter( $operational_clean, static function ( $value ) {
			return '' !== trim( (string) $value );
		} ) ) && ! empty( $existing_operational ) ) {
			$operational_clean = $existing_operational;
		}
		if ( empty( $days_clean ) && ! empty( $existing_days ) ) {
			$days_clean = $existing_days;
		}

		update_post_meta( $post_id, '_oksia_trip_overview', $trip_clean );
		update_post_meta( $post_id, '_oksia_client_phone', trim( $trip_clean['country_code'] . ' ' . $trip_clean['phone'] ) );
		update_post_meta( $post_id, '_oksia_client_email', $trip_clean['email'] );
		update_post_meta( $post_id, '_oksia_source_brief', $source_brief_clean );
		update_post_meta( $post_id, '_oksia_quote_details', $quote_clean );
		update_post_meta( $post_id, '_oksia_hotel_plan', $hotel_clean );
		update_post_meta( $post_id, '_oksia_documents', $documents_clean );
		update_post_meta( $post_id, '_oksia_operational_notes', $operational_clean );
		update_post_meta( $post_id, '_oksia_days', $days_clean );
		update_post_meta( $post_id, '_oksia_intake_source', 'agent_portal' );
		update_post_meta( $post_id, '_oksia_submitted_by_user', get_current_user_id() );
		update_post_meta( $post_id, '_oksia_ai_status', 'Agent workspace submission saved.' );

		$stored_ai_hash = trim( (string) get_post_meta( $post_id, '_oksia_ai_input_hash', true ) );
		$should_generate_ai = $force_ai || '' === $stored_ai_hash || ! hash_equals( $stored_ai_hash, $ai_input_hash );

		if ( $should_generate_ai ) {
			$ai_result = $this->generate_agent_itinerary_draft( $post_id );
			if ( is_wp_error( $ai_result ) ) {
				$this->redirect_agent_workspace_with_error( $ai_result->get_error_message() );
			}

			update_post_meta( $post_id, '_oksia_ai_input_hash', $ai_input_hash );
			update_post_meta( $post_id, '_oksia_ai_status', __( 'Draft generated. Review the day-wise itinerary and attach images where needed.', 'oksia-smart-itinerary-agent' ) );
		} else {
			update_post_meta( $post_id, '_oksia_ai_status', __( 'Draft reused from the last generated brief.', 'oksia-smart-itinerary-agent' ) );
		}

		if ( class_exists( 'OKSIA_Admin' ) ) {
			OKSIA_Admin::sync_quote_version_meta( $post_id, get_current_user_id() );
		}

		update_post_meta( $post_id, '_oksia_quote_stage', 'send' );
		update_post_meta( $post_id, '_oksia_quote_status', __( 'Quote sent for review.', 'oksia-smart-itinerary-agent' ) );
		update_post_meta( $post_id, '_oksia_ai_status', __( 'Quote sent for review. Opened in browser view for verification.', 'oksia-smart-itinerary-agent' ) );

		$redirect = add_query_arg(
			array(
				'oksia_open_quote_view' => $post_id,
				'oksia_dashboard_notice' => 'quote_generated',
			),
			$this->get_workspace_dashboard_url()
		);
		if ( ! $redirect ) {
			$redirect = class_exists( 'OKSIA_Admin' ) && method_exists( 'OKSIA_Admin', 'get_quote_view_url' )
				? OKSIA_Admin::get_quote_view_url( $post_id )
				: get_permalink( $post_id );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	public function redirect_agent_login() {
		$login_url = wp_login_url( $this->current_url() );
		wp_safe_redirect( $login_url );
		exit;
	}

	private function get_workspace_dashboard_url() {
		$plugin = OKSIA_Smart_Itinerary_Agent_Plugin::instance();
		if ( $plugin && ! empty( $plugin->workspace ) && method_exists( $plugin->workspace, 'get_dashboard_url' ) ) {
			return $plugin->workspace->get_dashboard_url();
		}

		return home_url( '/oksia-dashboard/' );
	}

	private function render_agent_day_row( $index, $day, $is_template = false ) {
		$image_id = isset( $day['image_id'] ) ? absint( $day['image_id'] ) : 0;
		$image_url = isset( $day['image_url'] ) ? $day['image_url'] : '';
		?>
		<div class="oksia-day-card<?php echo $is_template ? ' oksia-day-card--template' : ''; ?>">
			<input type="hidden" class="oksia-day-image-id" name="oksia_days[<?php echo esc_attr( $index ); ?>][image_id]" value="<?php echo esc_attr( (string) $image_id ); ?>" />
			<input type="hidden" class="oksia-day-image-url" name="oksia_days[<?php echo esc_attr( $index ); ?>][image_url]" value="<?php echo esc_attr( $image_url ); ?>" />
			<div class="oksia-grid oksia-grid--two">
				<p><label><?php esc_html_e( 'Day Title', 'oksia-smart-itinerary-agent' ); ?></label><input type="text" name="oksia_days[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $day['title'] ?? '' ); ?>" class="widefat" /></p>
				<p><label><?php esc_html_e( 'Location / Activity', 'oksia-smart-itinerary-agent' ); ?></label><input type="text" name="oksia_days[<?php echo esc_attr( $index ); ?>][location]" value="<?php echo esc_attr( $day['location'] ?? '' ); ?>" class="widefat" /></p>
			</div>
			<p><label><?php esc_html_e( 'Description', 'oksia-smart-itinerary-agent' ); ?></label><textarea name="oksia_days[<?php echo esc_attr( $index ); ?>][description]" rows="4" class="widefat"><?php echo esc_textarea( $day['description'] ?? '' ); ?></textarea></p>
			<p><label><?php esc_html_e( 'Logistics', 'oksia-smart-itinerary-agent' ); ?></label><textarea name="oksia_days[<?php echo esc_attr( $index ); ?>][logistics]" rows="3" class="widefat"><?php echo esc_textarea( $day['logistics'] ?? '' ); ?></textarea></p>
			<p class="oksia-upload-row"><button type="button" class="button oksia-upload-day-image"><?php esc_html_e( 'Choose Day Image', 'oksia-smart-itinerary-agent' ); ?></button><span class="oksia-day-image-preview"><?php echo esc_html( $image_url ? basename( $image_url ) : __( 'No image selected', 'oksia-smart-itinerary-agent' ) ); ?></span></p>
			<p><button type="button" class="button-link-delete oksia-remove-row"><?php esc_html_e( 'Remove day', 'oksia-smart-itinerary-agent' ); ?></button></p>
		</div>
		<?php
	}

	/* -------------------------------------------------------------------------
	 * Agent notification email
	 * ---------------------------------------------------------------------- */

	private function notify_agent( $post_id, $quote_id, $name, $phone, $email, $type, $dest, $start, $end, $nights, $adults, $c611, $inf ) {
		$to      = get_option( 'oksia_billing_email', get_option( 'admin_email' ) );
		$agency  = get_option( 'oksia_agency_name', 'OK' );
		$subject = '[' . $agency . '] New inquiry - ' . $quote_id . ' - ' . $name . ' - ' . $dest;

		$edit_url = $this->get_agent_intake_url( $quote_id );

		$pax_parts   = array( $adults . ' adult' . ( $adults > 1 ? 's' : '' ) );
		if ( $c611 > 0 ) $pax_parts[] = $c611 . ' child' . ( $c611 > 1 ? 'ren' : '' ) . ' (6-11)';
		if ( $inf > 0 )  $pax_parts[] = $inf . ' infant' . ( $inf > 1 ? 's' : '' );

		$body  = "New client inquiry received.\r\n\r\n";
		$body .= "Quote ID   : " . $quote_id . "\r\n";
		$body .= "Client     : " . $name . "\r\n";
		$body .= "Phone      : +91 " . $phone . "\r\n";
		$body .= "Email      : " . $email . "\r\n";
		$body .= "Trip type  : " . $type . "\r\n";
		$body .= "Destination: " . $dest . "\r\n";
		$body .= "Start date : " . $start . "\r\n";
		$body .= "End date   : " . $end . "\r\n";
		$body .= "Nights     : " . $nights . "\r\n";
		$body .= "Travellers : " . implode( ', ', $pax_parts ) . "\r\n\r\n";
		$body .= "Open in Agent Intake:\r\n" . $edit_url . "\r\n";

		wp_mail( $to, $subject, $body );
	}

	/* -------------------------------------------------------------------------
	 * Quote ID generator (mirrors admin class logic, static-safe)
	 * ---------------------------------------------------------------------- */

	public static function generate_next_quote_id() {
		global $wpdb;

		$date_part = wp_date( 'ymd', current_time( 'timestamp' ) );
		$prefix    = 'OK' . $date_part;
		$like      = $wpdb->esc_like( $prefix ) . '%';
		$meta_key  = '_oksia_quote_id';

		$existing_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
				$meta_key,
				$like
			)
		);

		$max = 0;
		foreach ( (array) $existing_ids as $id ) {
			if ( 0 === strpos( (string) $id, $prefix ) ) {
				$seq = (int) substr( (string) $id, strlen( $prefix ) );
				if ( $seq > $max ) {
					$max = $seq;
				}
			}
		}

		return $prefix . str_pad( (string) ( $max + 1 ), 2, '0', STR_PAD_LEFT );
	}

	/* -------------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------------- */

	private function current_url() {
		if ( empty( $_SERVER['HTTP_HOST'] ) ) {
			return home_url( '/' );
		}

		$scheme = is_ssl() ? 'https://' : 'http://';
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		return esc_url_raw( $scheme . wp_unslash( $_SERVER['HTTP_HOST'] ) . $request_uri );
	}

	private function redirect_agent_workspace_with_error( $message, $fields = array() ) {
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$args = array(
			'oksia_error' => sanitize_text_field( $message ),
		);
		if ( ! empty( $fields ) ) {
			$args['oksia_error_fields'] = implode( ',', array_map( 'sanitize_key', (array) $fields ) );
		}
		$redirect = add_query_arg( $args, $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}

	private function render_agency_login_form( $redirect_to = '' ) {
		$redirect_to = $redirect_to ? $redirect_to : $this->current_url();
		?>
		<?php
		wp_login_form( array(
			'redirect' => $redirect_to,
			'remember' => true,
		) );
		?>
		<?php
	}

	private function generate_agent_itinerary_draft( $post_id ) {
		if ( ! class_exists( 'OKSIA_Smart_Itinerary_Agent_Plugin' ) ) {
			update_post_meta( $post_id, '_oksia_ai_status', __( 'AI service is not available.', 'oksia-smart-itinerary-agent' ) );
			return new WP_Error( 'oksia_ai_unavailable', __( 'AI service is not available.', 'oksia-smart-itinerary-agent' ) );
		}

		$plugin = OKSIA_Smart_Itinerary_Agent_Plugin::instance();
		if ( ! $plugin || empty( $plugin->ai_service ) || ! method_exists( $plugin->ai_service, 'generate_itinerary_draft' ) ) {
			update_post_meta( $post_id, '_oksia_ai_status', __( 'AI service is not available.', 'oksia-smart-itinerary-agent' ) );
			return new WP_Error( 'oksia_ai_unavailable', __( 'AI service is not available.', 'oksia-smart-itinerary-agent' ) );
		}

		$draft = $plugin->ai_service->generate_itinerary_draft( $post_id );
		if ( is_wp_error( $draft ) ) {
			update_post_meta( $post_id, '_oksia_ai_status', sprintf( __( 'AI error: %s', 'oksia-smart-itinerary-agent' ), $draft->get_error_message() ) );
			return $draft;
		}

		$existing_operational = (array) get_post_meta( $post_id, '_oksia_operational_notes', true );
		$operational = array(
			'summary' => sanitize_textarea_field( $draft['summary'] ?? '' ),
			'inclusions' => sanitize_textarea_field( implode( "\n", $draft['inclusions'] ?? array() ) ),
			'exclusions' => sanitize_textarea_field( implode( "\n", $draft['exclusions'] ?? array() ) ),
			'important_notes' => sanitize_textarea_field( $draft['important_notes'] ?? '' ),
			'child_policy' => $existing_operational['child_policy'] ?? '',
			'booking_policy' => $existing_operational['booking_policy'] ?? '',
			'cancellation_policy' => $existing_operational['cancellation_policy'] ?? '',
		);

		update_post_meta( $post_id, '_oksia_operational_notes', $operational );
		update_post_meta( $post_id, '_oksia_days', $this->sanitize_day_data( $draft['days'] ?? array() ) );

		return true;
	}

	private function build_agent_ai_input_hash( $trip_clean, $source_brief_clean, $days_clean ) {
		$context = array(
			'destination'       => sanitize_text_field( (string) ( $trip_clean['destination'] ?? '' ) ),
			'trip_type'         => sanitize_text_field( (string) ( $trip_clean['trip_type'] ?? '' ) ),
			'total_nights'      => absint( $trip_clean['total_nights'] ?? 0 ),
			'adults'            => absint( $trip_clean['adults'] ?? 0 ),
			'adult_with_bed'    => absint( $trip_clean['adult_with_bed'] ?? 0 ),
			'child_without_bed' => absint( $trip_clean['child_without_bed'] ?? 0 ),
			'travelers'         => absint( $trip_clean['travelers'] ?? 0 ),
			'source_brief'      => sanitize_textarea_field( (string) $source_brief_clean ),
			'day_inputs'        => $this->normalize_agent_ai_day_inputs( $days_clean ),
		);

		return sha1( wp_json_encode( $context ) );
	}

	private function normalize_agent_ai_day_inputs( $days_clean ) {
		$normalized = array();

		foreach ( (array) $days_clean as $day ) {
			if ( ! is_array( $day ) ) {
				continue;
			}

			$normalized[] = array(
				'title'    => sanitize_text_field( (string) ( $day['title'] ?? '' ) ),
				'location' => sanitize_text_field( (string) ( $day['location'] ?? '' ) ),
			);
		}

		usort(
			$normalized,
			static function ( $left, $right ) {
				$left_key  = strtolower( trim( (string) ( $left['title'] . '|' . $left['location'] ) ) );
				$right_key = strtolower( trim( (string) ( $right['title'] . '|' . $right['location'] ) ) );

				return strcmp( $left_key, $right_key );
			}
		);

		return $normalized;
	}

	private function get_agent_intake_url( $quote_id = '' ) {
		$page_id = absint( get_option( 'oksia_agent_intake_page_id', 0 ) );
		$url = $page_id ? get_permalink( $page_id ) : home_url( '/agent-intake/' );
		if ( ! $url ) {
			$url = home_url( '/agent-intake/' );
		}

		if ( '' !== $quote_id ) {
			return add_query_arg(
				array(
					'oksia_agent_mode' => 'open',
					'oksia_quote_id'   => $quote_id,
				),
				$url
			);
		}

		return add_query_arg( 'oksia_agent_mode', 'new', $url );
	}

	private function get_main_agency_user_id() {
		$user_id = absint( get_option( 'oksia_main_agency_user_id', 0 ) );
		if ( $user_id > 0 && get_user_by( 'id', $user_id ) ) {
			return $user_id;
		}

		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => array( 'ID' ),
				'orderby' => 'ID',
				'order'  => 'ASC',
			)
		);

		if ( ! empty( $admins ) && ! empty( $admins[0]->ID ) ) {
			return absint( $admins[0]->ID );
		}

		return get_current_user_id();
	}

	private function get_current_agency_id() {
		if ( class_exists( 'OKSIA_Agencies' ) ) {
			$agency_id = OKSIA_Agencies::instance()->get_current_user_agency_id();
			if ( $agency_id > 0 ) {
				return $agency_id;
			}
		}

		$main_user_id = $this->get_main_agency_user_id();
		if ( class_exists( 'OKSIA_Agencies' ) ) {
			$agency_id = OKSIA_Agencies::instance()->get_current_user_agency_id( $main_user_id );
			if ( $agency_id > 0 ) {
				return $agency_id;
			}
		}

		if ( class_exists( 'OKSIA_Agencies' ) ) {
			$primary_id = absint( get_option( OKSIA_Agencies::OPTION_PRIMARY_AGENCY_ID, 0 ) );
			if ( $primary_id > 0 ) {
				return $primary_id;
			}
		}

		return 0;
	}

	private function find_submission_by_quote_id( $quote_id ) {
		$quote_id = sanitize_text_field( $quote_id );
		if ( '' === $quote_id ) {
			return 0;
		}

		$query = new WP_Query(
			array(
				'post_type'      => OKSIA_Post_Types::POST_TYPE,
				'post_status'    => array( 'draft', 'pending', 'publish' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_oksia_quote_id',
						'value' => $quote_id,
					),
				),
			)
		);

		if ( ! empty( $query->posts[0] ) ) {
			return absint( $query->posts[0] );
		}

		if ( ctype_digit( $quote_id ) ) {
			$post_id = absint( $quote_id );
			if ( $post_id > 0 && OKSIA_Post_Types::POST_TYPE === get_post_type( $post_id ) ) {
				return $post_id;
			}
		}

		return 0;
	}

	private function get_setting_options( $option_name, $fallback ) {
		$value = (string) get_option( $option_name, '' );
		$items = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $value ) ) );
		return ! empty( $items ) ? array_values( $items ) : $fallback;
	}

	private function get_trip_type_setting_options( $domestic_option, $international_option, $fallback ) {
		return array(
			'Domestic' => $this->get_setting_options( $domestic_option, $fallback ),
			'International' => $this->get_setting_options( $international_option, $fallback ),
		);
	}

	private function calculate_nights( $start_date, $end_date ) {
		if ( ! $start_date || ! $end_date ) {
			return 0;
		}

		try {
			$start = new DateTimeImmutable( $start_date );
			$end   = new DateTimeImmutable( $end_date );
			$diff  = $start->diff( $end );
			return max( 0, (int) $diff->days );
		} catch ( Exception $exception ) {
			return 0;
		}
	}

	private function sanitize_decimal( $value, $default ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		$value = preg_replace( '/[^0-9.\-]/', '', $value );
		return '' === $value ? (string) $default : $value;
	}

	private function get_currency_snapshot_rates_inr() {
		$rates = array();
		if ( ! class_exists( 'OKSIA_Workspace' ) ) {
			return $rates;
		}

		$snapshot = get_option( OKSIA_Workspace::OPTION_CURRENCY_SNAPSHOT, array() );
		$current = (array) ( $snapshot['current'] ?? array() );
		foreach ( $current as $code => $row ) {
			$currency = strtoupper( preg_replace( '/[^A-Z]/', '', (string) $code ) );
			if ( '' === $currency ) {
				continue;
			}

			$value = is_array( $row ) ? ( $row['value'] ?? '' ) : '';
			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}

			$numeric = (float) preg_replace( '/[^0-9.\-]/', '', $value );
			if ( $numeric > 0 ) {
				$rates[ $currency ] = $numeric;
			}
		}

		return $rates;
	}

	private function sanitize_agent_quote( $quote, $trip_type ) {
		$vendor_mode = sanitize_key( $quote['vendor_mode'] ?? 'multi' );
		if ( ! in_array( $vendor_mode, array( 'single', 'multi' ), true ) ) {
			$vendor_mode = 'multi';
		}
		$is_international = 'International' === $trip_type;

		$travel_mode = sanitize_text_field( $quote['travel_mode'] ?? 'Flight' );
		if ( ! in_array( $travel_mode, array( 'Flight', 'Railway', 'Bus' ), true ) ) {
			$travel_mode = 'Flight';
		}

		return array(
			'vendor_mode' => $vendor_mode,
			'travel_mode' => $travel_mode,
			'hotel_category' => sanitize_text_field( $quote['hotel_category'] ?? '' ),
			'occupancy' => sanitize_text_field( $quote['occupancy'] ?? '' ),
			'rooms' => absint( $quote['rooms'] ?? 0 ),
			'meal_plan' => sanitize_text_field( $quote['meal_plan'] ?? '' ),
			'meal_transfers' => 'International' === $trip_type ? sanitize_text_field( $quote['meal_transfers'] ?? '' ) : '',
			'pickup_from' => sanitize_text_field( $quote['pickup_from'] ?? '' ),
			'drop_to' => sanitize_text_field( $quote['drop_to'] ?? '' ),
			'first_transfer' => sanitize_text_field( $quote['first_transfer'] ?? '' ),
			'last_transfer' => sanitize_text_field( $quote['last_transfer'] ?? '' ),
			'sightseeing_vehicle' => sanitize_text_field( $quote['sightseeing_vehicle'] ?? '' ),
			'vehicle_type' => sanitize_text_field( $quote['vehicle_type'] ?? '' ),
			'transfer_note' => sanitize_text_field( $quote['transfer_note'] ?? '' ),
			'currency' => sanitize_text_field( $quote['currency'] ?? 'INR' ),
			'multi_currency' => sanitize_text_field( $quote['multi_currency'] ?? ( $quote['currency'] ?? 'INR' ) ),
			'exchange_rate' => sanitize_text_field( $quote['exchange_rate'] ?? '' ),
			'transaction_cost' => $this->sanitize_decimal( $quote['transaction_cost'] ?? '1.9', 1.9 ),
			'additional_cost' => $this->sanitize_decimal( $quote['additional_cost'] ?? '0', 0 ),
			'effective_rate' => sanitize_text_field( $quote['effective_rate'] ?? '' ),
			'adult_rate' => $this->sanitize_decimal( $quote['adult_rate'] ?? '0', 0 ),
			'with_bed_rate' => $this->sanitize_decimal( $quote['with_bed_rate'] ?? '0', 0 ),
			'child_rate' => $this->sanitize_decimal( $quote['child_rate'] ?? '0', 0 ),
			'adult_markup' => $this->sanitize_decimal( $quote['adult_markup'] ?? '0', 0 ),
			'with_bed_markup' => $this->sanitize_decimal( $quote['with_bed_markup'] ?? '0', 0 ),
			'child_markup' => $this->sanitize_decimal( $quote['child_markup'] ?? '0', 0 ),
			'single_txn_adult' => $this->sanitize_decimal( $quote['single_txn_adult'] ?? '', '' ),
			'single_txn_with_bed' => $this->sanitize_decimal( $quote['single_txn_with_bed'] ?? '', '' ),
			'single_txn_child' => $this->sanitize_decimal( $quote['single_txn_child'] ?? '', '' ),
			'adult_rate_quote' => sanitize_text_field( $quote['adult_rate_quote'] ?? '' ),
			'with_bed_rate_quote' => sanitize_text_field( $quote['with_bed_rate_quote'] ?? '' ),
			'child_rate_quote' => sanitize_text_field( $quote['child_rate_quote'] ?? '' ),
			'multi_flight_adult' => $this->sanitize_decimal( $quote['multi_flight_adult'] ?? '', '' ),
			'multi_flight_with_bed' => $this->sanitize_decimal( $quote['multi_flight_with_bed'] ?? '', '' ),
			'multi_flight_child' => $this->sanitize_decimal( $quote['multi_flight_child'] ?? '', '' ),
			'multi_hotel_adult' => $this->sanitize_decimal( $quote['multi_hotel_adult'] ?? '', '' ),
			'multi_hotel_with_bed' => $this->sanitize_decimal( $quote['multi_hotel_with_bed'] ?? '', '' ),
			'multi_hotel_child' => $this->sanitize_decimal( $quote['multi_hotel_child'] ?? '', '' ),
			'multi_transportation_adult' => $this->sanitize_decimal( $quote['multi_transportation_adult'] ?? '', '' ),
			'multi_transportation_with_bed' => $this->sanitize_decimal( $quote['multi_transportation_with_bed'] ?? '', '' ),
			'multi_transportation_child' => $this->sanitize_decimal( $quote['multi_transportation_child'] ?? '', '' ),
			'multi_visa_adult' => $is_international ? $this->sanitize_decimal( $quote['multi_visa_adult'] ?? '', '' ) : '',
			'multi_visa_with_bed' => $is_international ? $this->sanitize_decimal( $quote['multi_visa_with_bed'] ?? '', '' ) : '',
			'multi_visa_child' => $is_international ? $this->sanitize_decimal( $quote['multi_visa_child'] ?? '', '' ) : '',
			'multi_tourism_tax_adult' => $is_international ? $this->sanitize_decimal( $quote['multi_tourism_tax_adult'] ?? '', '' ) : '',
			'multi_tourism_tax_with_bed' => $is_international ? $this->sanitize_decimal( $quote['multi_tourism_tax_with_bed'] ?? '', '' ) : '',
			'multi_tourism_tax_child' => $is_international ? $this->sanitize_decimal( $quote['multi_tourism_tax_child'] ?? '', '' ) : '',
			'multi_tip_adult' => $is_international ? $this->sanitize_decimal( $quote['multi_tip_adult'] ?? '', '' ) : '',
			'multi_tip_with_bed' => $is_international ? $this->sanitize_decimal( $quote['multi_tip_with_bed'] ?? '', '' ) : '',
			'multi_tip_child' => $is_international ? $this->sanitize_decimal( $quote['multi_tip_child'] ?? '', '' ) : '',
			'multi_adult_markup' => $this->sanitize_decimal( $quote['multi_adult_markup'] ?? '', '' ),
			'multi_with_bed_markup' => $this->sanitize_decimal( $quote['multi_with_bed_markup'] ?? '', '' ),
			'multi_child_markup' => $this->sanitize_decimal( $quote['multi_child_markup'] ?? '', '' ),
			'multi_txn_adult' => $this->sanitize_decimal( $quote['multi_txn_adult'] ?? '', '' ),
			'multi_txn_with_bed' => $this->sanitize_decimal( $quote['multi_txn_with_bed'] ?? '', '' ),
			'multi_txn_child' => $this->sanitize_decimal( $quote['multi_txn_child'] ?? '', '' ),
			'multi_adult_final' => sanitize_text_field( $quote['multi_adult_final'] ?? '' ),
			'multi_with_bed_final' => sanitize_text_field( $quote['multi_with_bed_final'] ?? '' ),
			'multi_child_final' => sanitize_text_field( $quote['multi_child_final'] ?? '' ),
			'multi_adult_rate_quote' => sanitize_text_field( $quote['multi_adult_rate_quote'] ?? '' ),
			'multi_with_bed_rate_quote' => sanitize_text_field( $quote['multi_with_bed_rate_quote'] ?? '' ),
			'multi_child_rate_quote' => sanitize_text_field( $quote['multi_child_rate_quote'] ?? '' ),
			'package_base_total' => $this->sanitize_decimal( $quote['package_base_total'] ?? '0', 0 ),
			'package_customer_total' => $this->sanitize_decimal( $quote['package_customer_total'] ?? '0', 0 ),
		);
	}

	private function sanitize_hotel_plan_data( $hotel_plan ) {
		$clean = array();
		foreach ( (array) $hotel_plan as $stay ) {
			$row = array(
				'city' => sanitize_text_field( $stay['city'] ?? '' ),
				'hotel' => sanitize_text_field( $stay['hotel'] ?? '' ),
				'nights' => absint( $stay['nights'] ?? 0 ),
			);
			if ( '' === $row['city'] && '' === $row['hotel'] && 0 === $row['nights'] ) {
				continue;
			}
			$clean[] = $row;
		}
		return $clean;
	}

	private function get_hotel_plan_nights_total( $hotel_plan ) {
		$total = 0;
		foreach ( (array) $hotel_plan as $stay ) {
			$total += absint( $stay['nights'] ?? 0 );
		}
		return $total;
	}

	private function sanitize_document_data( $documents ) {
		$clean = array();
		foreach ( (array) $documents as $document ) {
			$row = array(
				'attachment_id' => absint( $document['attachment_id'] ?? 0 ),
				'title' => sanitize_text_field( $document['title'] ?? '' ),
				'type' => sanitize_text_field( $document['type'] ?? '' ),
				'url' => esc_url_raw( $document['url'] ?? '' ),
				'notes' => sanitize_textarea_field( $document['notes'] ?? '' ),
			);
			if ( ! $row['attachment_id'] && '' === $row['title'] && '' === $row['url'] && '' === $row['notes'] ) {
				continue;
			}
			$clean[] = $row;
		}
		return $clean;
	}

	private function sanitize_operational_data( $operational ) {
		return array(
			'summary' => sanitize_textarea_field( $operational['summary'] ?? '' ),
			'inclusions' => sanitize_textarea_field( $operational['inclusions'] ?? '' ),
			'exclusions' => sanitize_textarea_field( $operational['exclusions'] ?? '' ),
			'important_notes' => sanitize_textarea_field( $operational['important_notes'] ?? '' ),
			'child_policy' => sanitize_textarea_field( $operational['child_policy'] ?? '' ),
			'booking_policy' => sanitize_textarea_field( $operational['booking_policy'] ?? '' ),
			'cancellation_policy' => sanitize_textarea_field( $operational['cancellation_policy'] ?? '' ),
		);
	}

	private function sanitize_day_data( $days ) {
		$clean = array();
		foreach ( (array) $days as $day ) {
			$row = array(
				'title' => sanitize_text_field( $day['title'] ?? '' ),
				'location' => sanitize_text_field( $day['location'] ?? '' ),
				'description' => sanitize_textarea_field( $day['description'] ?? '' ),
				'logistics' => sanitize_textarea_field( $day['logistics'] ?? '' ),
				'image_id' => absint( $day['image_id'] ?? 0 ),
				'image_url' => esc_url_raw( $day['image_url'] ?? '' ),
			);
			if ( '' === $row['title'] && '' === $row['description'] && '' === $row['logistics'] ) {
				continue;
			}
			$clean[] = $row;
		}
		return $clean;
	}

	private function get_destination_list( $option_name ) {
		$value = (string) get_option( $option_name, '' );
		$items = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $value ) ) );
		return array_values( $items );
	}
}

