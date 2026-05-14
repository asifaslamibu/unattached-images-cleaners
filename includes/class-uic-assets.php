<?php
/**
 * Shared admin assets.
 *
 * @package UnattachedImagesCleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UIC_Assets {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * @param string $hook
	 */
	public function enqueue( $hook ) {
		// All our screens live under the top-level "uic-dashboard" menu.
		$our_screens = array(
			'toplevel_page_' . UIC_MENU_SLUG,
			'media-tools_page_unattached-images-cleaner',
			'media-tools_page_uic-webp-converter',
		);
		if ( ! in_array( $hook, $our_screens, true ) ) {
			return;
		}

		$css = '
			.uic-dashboard .uic-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin:18px 0;}
			.uic-dashboard .uic-stat{background:#fff;border:1px solid #dcdcde;border-left:4px solid #2271b1;border-radius:4px;padding:18px;}
			.uic-dashboard .uic-stat-warning{border-left-color:#dba617;}
			.uic-dashboard .uic-stat-success{border-left-color:#00a32a;}
			.uic-dashboard .uic-stat-value{font-size:28px;font-weight:600;line-height:1;}
			.uic-dashboard .uic-stat-label{color:#646970;font-size:13px;margin-top:6px;}
			.uic-dashboard .uic-bar{display:inline-block;width:60px;height:6px;background:#e0e0e0;border-radius:3px;position:relative;margin-left:8px;vertical-align:middle;}
			.uic-dashboard .uic-bar::after{content:"";position:absolute;left:0;top:0;height:100%;background:#2271b1;border-radius:3px;width:var(--p,0%);}
			.uic-dashboard .uic-format-table{margin-bottom:24px;max-width:700px;}
			.uic-dashboard .uic-env-table{max-width:700px;}
			.uic-dashboard .uic-env-table td:first-child{font-weight:600;width:220px;}
			.uic-dashboard .uic-ok{color:#00a32a;font-weight:600;}
			.uic-dashboard .uic-bad{color:#d63638;font-weight:600;}

			.uic-tools{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin:12px 0 24px;}
			.uic-tool-card{display:block;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:20px;text-decoration:none;color:inherit;transition:transform .12s,box-shadow .12s;}
			.uic-tool-card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.08);}
			.uic-tool-card .dashicons{font-size:32px;width:32px;height:32px;color:#2271b1;}
			.uic-tool-card h3{margin:8px 0 6px;}
			.uic-tool-card p{color:#646970;margin:0 0 14px;min-height:40px;}

			.uic-filter-form{background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:14px;margin:14px 0;}
			.uic-filter-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:8px;}
			.uic-filter-row:last-child{margin-bottom:0;}
			.uic-chip{display:inline-flex;align-items:center;gap:6px;background:#f0f0f1;border:1px solid #c3c4c7;border-radius:14px;padding:4px 10px;cursor:pointer;font-size:13px;}
			.uic-chip:has(input:checked){background:#e7f1fb;border-color:#2271b1;color:#0a4b78;}
			.uic-chip input{margin:0;}
			.uic-reset{margin-left:6px;}

			.uic-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-top:18px;}
			.uic-card{background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:10px;position:relative;cursor:pointer;}
			.uic-card img{max-width:100%;height:140px;object-fit:cover;display:block;border-radius:2px;}
			.uic-card .uic-filename{font-size:12px;margin-top:6px;word-break:break-all;}
			.uic-card .uic-meta{font-size:11px;color:#646970;margin-top:4px;}
			.uic-card input[type=checkbox]{position:absolute;top:14px;left:14px;width:20px;height:20px;}
			.uic-card.is-selected{outline:2px solid #2271b1;}

			.uic-toolbar{display:flex;gap:14px;align-items:center;margin:14px 0;flex-wrap:wrap;background:#fff;padding:12px;border:1px solid #dcdcde;border-radius:4px;}
			.uic-toolbar label{display:inline-flex;gap:6px;align-items:center;}
			.uic-empty{padding:30px;text-align:center;color:#646970;background:#fff;border:1px solid #dcdcde;border-radius:4px;}
			.uic-quality{display:inline-flex;gap:8px;align-items:center;}
			.uic-quality output{font-weight:600;min-width:28px;display:inline-block;}
			.uic-warn{background:#fcf9e8;border-left:4px solid #dba617;padding:10px 14px;margin:14px 0;}
			.uic-error{background:#fcf0f1;border-left:4px solid #d63638;padding:10px 14px;margin:14px 0;}
			.uic-divider{width:1px;height:24px;background:#dcdcde;}
		';
		wp_register_style( 'uic-admin', false, array(), UIC_VERSION );
		wp_enqueue_style( 'uic-admin' );
		wp_add_inline_style( 'uic-admin', $css );

		$msg_pick     = wp_json_encode( __( 'Select at least one image, or use "Select all matching".', 'unattached-images-cleaner' ) );
		$msg_del_sel  = wp_json_encode( __( 'Permanently delete %d selected image(s)? This cannot be undone.', 'unattached-images-cleaner' ) );
		$msg_del_all  = wp_json_encode( __( 'Permanently delete ALL matching unattached images (up to the batch limit per submit)? This cannot be undone.', 'unattached-images-cleaner' ) );
		$msg_conv_sel = wp_json_encode( __( 'Convert %d selected image(s) to WebP?', 'unattached-images-cleaner' ) );
		$msg_conv_all = wp_json_encode( __( 'Convert ALL matching images to WebP (up to the batch limit per submit)?', 'unattached-images-cleaner' ) );

		$js = "
			document.addEventListener('DOMContentLoaded',function(){
				// Select-all-on-page checkbox.
				var selectAll = document.getElementById('uic-select-all');
				if(selectAll){
					selectAll.addEventListener('change',function(){
						document.querySelectorAll('input[name=\"ids[]\"]').forEach(function(cb){
							cb.checked = selectAll.checked;
							cb.closest('.uic-card').classList.toggle('is-selected', cb.checked);
						});
					});
				}
				document.querySelectorAll('input[name=\"ids[]\"]').forEach(function(cb){
					cb.addEventListener('change',function(){
						cb.closest('.uic-card').classList.toggle('is-selected', cb.checked);
					});
				});

				// Quality slider live readout.
				var q = document.getElementById('uic-quality');
				var qOut = document.getElementById('uic-quality-output');
				if(q && qOut){ q.addEventListener('input',function(){ qOut.textContent = q.value; }); }

				// Generic action-form handler: covers delete + convert, both
				// 'selected' and 'select all matching' submit buttons.
				function attachFormHandler(formId, kind){
					var form = document.getElementById(formId);
					if(!form) return;
					form.addEventListener('submit',function(e){
						var submitter = e.submitter || document.activeElement;
						var isSelectAll = submitter && submitter.name === 'select_all_matching';
						var n = document.querySelectorAll('input[name=\"ids[]\"]:checked').length;

						if(!isSelectAll && n === 0){
							e.preventDefault();
							alert({$msg_pick});
							return;
						}

						var msg;
						if(kind === 'delete'){
							msg = isSelectAll ? {$msg_del_all} : {$msg_del_sel}.replace('%d', n);
						} else {
							msg = isSelectAll ? {$msg_conv_all} : {$msg_conv_sel}.replace('%d', n);
						}
						if(!confirm(msg)) e.preventDefault();
					});
				}
				attachFormHandler('uic-form', 'delete');
				attachFormHandler('uic-webp-form', 'convert');
			});
		";
		wp_register_script( 'uic-admin', '', array(), UIC_VERSION, true );
		wp_enqueue_script( 'uic-admin' );
		wp_add_inline_script( 'uic-admin', $js );
	}
}
