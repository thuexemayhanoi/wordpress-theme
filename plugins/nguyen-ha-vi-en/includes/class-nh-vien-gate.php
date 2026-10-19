<?php
/**
 * The gate of the English side: whether it is switched on, who decides, and how an administrator switches it.
 *
 * There are two ways to switch it on, and they are never mixed:
 *
 * 1. wp-config.php defines NH_VIEN_ENABLE_EN_ROUTES. The constant then decides alone: true opens the gate (on a
 *    production site only together with NH_VIEN_ALLOW_PRODUCTION), anything else closes it, whatever the dashboard
 *    says. That is also the emergency brake: define it as false and the English side is off.
 * 2. Without the constant, an administrator uses the buttons of Tools > VI/EN Links. The decision is one option, tied
 *    to the address of the site (a copy of the database on another address stays closed until somebody switches it on
 *    there), and on a production site it needs an explicit confirmation.
 *
 * Nothing here runs by itself: the gate only changes when an administrator presses a button or wp-config.php changes.
 * Installing, updating or activating the plugin never opens it, and deactivating it closes it again.
 *
 * @package Nguyen_Ha_VI_EN
 */

defined( 'ABSPATH' ) || exit;

/**
 * Policy and state of the gate, and the two operations that change the state.
 */
final class NH_VIEN_Gate {
	const OPTION = 'nh_vien_gate_v1';

	/**
	 * Whether the English side is switched on: the one answer behind NH_VIEN_Routes::enabled().
	 *
	 * @return bool
	 */
	public static function open() {
		if ( ! function_exists( 'wp_get_environment_type' ) ) {
			return false;
		}
		if ( self::managed_by_config() ) {
			return true === NH_VIEN_ENABLE_EN_ROUTES && self::environment_allows( false );
		}
		$state = self::state();
		return $state['on'] && self::environment_allows( $state['confirmed'] );
	}

	/**
	 * Whether wp-config.php decides (it defines NH_VIEN_ENABLE_EN_ROUTES, with any value).
	 *
	 * @return bool
	 */
	public static function managed_by_config() {
		return defined( 'NH_VIEN_ENABLE_EN_ROUTES' );
	}

	/**
	 * Whether the site is neither local, development nor staging: only then the extra consent is needed.
	 *
	 * @return bool
	 */
	public static function production() {
		return ! function_exists( 'wp_get_environment_type' ) || ! in_array( wp_get_environment_type(), array( 'local', 'development', 'staging' ), true );
	}

	/**
	 * Whether wp-config.php forbids switching the English side on for this production site.
	 *
	 * @return bool
	 */
	public static function production_denied_by_config() {
		return self::production() && defined( 'NH_VIEN_ALLOW_PRODUCTION' ) && true !== NH_VIEN_ALLOW_PRODUCTION;
	}

	/**
	 * Whether switching on needs the confirmation of the administrator: a production site that wp-config.php does not
	 * already vouch for.
	 *
	 * @return bool
	 */
	public static function confirmation_needed() {
		return self::production() && ! defined( 'NH_VIEN_ALLOW_PRODUCTION' );
	}

	/**
	 * What an administrator decided in the dashboard, checked against this site.
	 *
	 * @return array{on:bool,stale:bool,confirmed:bool,user:int,time:int} on: switched on here; stale: it was switched on
	 *         at another address of the site, so it counts as off; confirmed: the production confirmation was given.
	 */
	public static function state() {
		$state  = array(
			'on'        => false,
			'stale'     => false,
			'confirmed' => false,
			'user'      => 0,
			'time'      => 0,
		);
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			return $state;
		}
		$state['user'] = isset( $stored['user'] ) ? max( 0, (int) $stored['user'] ) : 0;
		$state['time'] = isset( $stored['time'] ) ? max( 0, (int) $stored['time'] ) : 0;
		if ( ! isset( $stored['on'] ) || true !== $stored['on'] ) {
			return $state;
		}
		if ( ! isset( $stored['site'] ) || self::site_key() !== $stored['site'] ) {
			$state['stale'] = true;
			return $state;
		}
		$state['on']        = true;
		$state['confirmed'] = isset( $stored['confirmed'] ) && true === $stored['confirmed'];
		return $state;
	}

	/**
	 * Switch the English side on: store the decision and the rewrite rules, or change nothing.
	 *
	 * @param bool $confirmed Whether the administrator ticked the confirmation (only a production site asks for it).
	 * @param int  $user_id   The administrator who switches it on.
	 * @return string Status token for the Tools screen: routes_enabled, or the reason nothing was changed.
	 */
	public static function switch_on( $confirmed, $user_id ) {
		if ( self::managed_by_config() ) {
			return 'nh_vien_config_controls';
		}
		if ( self::production_denied_by_config() ) {
			return 'nh_vien_production_denied';
		}
		if ( self::confirmation_needed() && true !== $confirmed ) {
			return 'nh_vien_confirm_required';
		}
		if ( ! NH_VIEN_Routes::pretty_permalinks() ) {
			return 'nh_vien_pretty_links_required';
		}
		// The English home would take the address /en/ away from a published post, or from the posts of a category called en.
		if ( NH_VIEN_Routes::address_taken() ) {
			return 'address_taken';
		}
		$previous = get_option( self::OPTION, null );
		update_option(
			self::OPTION,
			array(
				'on'        => true,
				'site'      => self::site_key(),
				'confirmed' => self::production() && true === $confirmed,
				'user'      => (int) $user_id,
				'time'      => time(),
			),
			true
		);
		NH_VIEN_Routes::reset();
		self::store_rules();
		NH_VIEN_Routes::reset();
		if ( ! self::open() || ! NH_VIEN_Routes::rules_stored() ) {
			self::restore( $previous );
			return 'nh_vien_rewrite_failed';
		}
		do_action( 'nh_vien_pairs_changed', 0, 0 );
		return 'routes_enabled';
	}

	/**
	 * Switch the English side off: store the decision and take the rules of this plugin (and only those) out of the stored
	 * rewrite rules. Closing always works, even when the rules cannot be removed: the closed gate answers 404 for them.
	 *
	 * @param int $user_id The administrator who switches it off.
	 * @return string Status token for the Tools screen.
	 */
	public static function switch_off( $user_id ) {
		if ( self::managed_by_config() ) {
			return 'nh_vien_config_controls';
		}
		update_option(
			self::OPTION,
			array(
				'on'   => false,
				'user' => (int) $user_id,
				'time' => time(),
			),
			true
		);
		NH_VIEN_Routes::reset();
		// update_option() also answers false when nothing had to change, so ask what is stored. A decision that is still on
		// must keep its rules: removing them would leave the gate open without them.
		$state = self::state();
		if ( $state['on'] ) {
			return 'nh_vien_gate_save_failed';
		}
		$removed = NH_VIEN_Routes::remove_stored_rules();
		NH_VIEN_Routes::reset();
		do_action( 'nh_vien_pairs_changed', 0, 0 );
		return $removed ? 'routes_disabled' : 'nh_vien_rules_left';
	}

	/**
	 * Forget the decision of the dashboard, so that activating the plugin again starts closed. Used on deactivation;
	 * nothing is written when there is nothing to forget.
	 */
	public static function forget() {
		if ( null !== get_option( self::OPTION, null ) ) {
			delete_option( self::OPTION );
		}
	}

	/**
	 * Whether the environment lets the gate open.
	 *
	 * @param bool $confirmed Whether the administrator confirmed the production switch-on.
	 * @return bool
	 */
	private static function environment_allows( $confirmed ) {
		if ( ! self::production() ) {
			return true;
		}
		if ( defined( 'NH_VIEN_ALLOW_PRODUCTION' ) ) {
			return true === NH_VIEN_ALLOW_PRODUCTION;
		}
		return $confirmed;
	}

	/**
	 * A short fingerprint of the address of the site. It is not a secret; it only tells a database that was copied to
	 * another address (a staging copy, a new domain, http to https) from the site where the decision was taken.
	 *
	 * @return string
	 */
	private static function site_key() {
		return substr( md5( rtrim( (string) get_option( 'home' ), '/' ) ), 0, 16 );
	}

	/**
	 * Put the decision back as it was after a switch-on that could not be completed.
	 *
	 * @param mixed $previous The stored value before the attempt, or null when there was none.
	 */
	private static function restore( $previous ) {
		if ( null === $previous ) {
			delete_option( self::OPTION );
		} else {
			update_option( self::OPTION, $previous, true );
		}
		NH_VIEN_Routes::reset();
		if ( self::open() ) {
			self::store_rules();
		} else {
			NH_VIEN_Routes::remove_stored_rules();
		}
		NH_VIEN_Routes::reset();
	}

	/**
	 * Store the rules of the plugin in a request that began with the gate closed (so they are not registered yet): put them
	 * on top of the stored rules, leaving every other rule as it is, and only when WordPress has no stored rules to extend,
	 * let it regenerate them with ours registered.
	 */
	public static function store_rules() {
		NH_VIEN_Routes::register();
		if ( ! NH_VIEN_Routes::store_rules() ) {
			flush_rewrite_rules( false );
		}
	}
}
