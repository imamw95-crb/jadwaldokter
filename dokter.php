<?php
/**
 * Plugin Name: Dokter Alodokter Style
 * Description: Manajemen data dokter dengan filter premium dan mobile friendly.
 * Version: 1.8
 */

// 1. Membuat Custom Post Type "Dokter"
add_action( 'init', 'dokter_cpt' );
function dokter_cpt() {
    register_post_type( 'dokter',
        array(
            'labels'      => array(
                'name'          => 'Dokter',
                'singular_name' => 'Dokter',
                'add_new'       => 'Tambah Dokter',
                'add_new_item'  => 'Tambah Dokter Baru',
                'edit_item'     => 'Edit Dokter',
                'all_items'     => 'Semua Dokter',
            ),
            'public'      => true,
            'has_archive' => false,
            'supports'    => array( 'title', 'thumbnail' ),
            'menu_icon'   => 'dashicons-businessperson',
            'show_in_rest' => true,
        )
    );
}

// 2. Meta box untuk data tambahan dokter
add_action( 'add_meta_boxes', 'dokter_meta_box' );
function dokter_meta_box() {
    add_meta_box( 'dokter_info', 'Informasi Dokter', 'dokter_meta_box_callback', 'dokter', 'normal', 'high' );
}

function dokter_meta_box_callback( $post ) {
    wp_nonce_field( 'dokter_save_meta', 'dokter_meta_nonce' );
    
    $spesialis      = get_post_meta( $post->ID, '_dokter_spesialis', true );
    $lokasi         = get_post_meta( $post->ID, '_dokter_lokasi', true );
    $rumah_sakit    = get_post_meta( $post->ID, '_dokter_rumah_sakit', true );
    $rating_persen  = get_post_meta( $post->ID, '_dokter_rating_persen', true );
    $paling_dicari  = get_post_meta( $post->ID, '_dokter_paling_dicari', true );
    $profil_singkat = get_post_meta( $post->ID, '_dokter_profil_singkat', true );
    ?>
    <style>
        .dokter-meta-row { margin-bottom: 15px; }
        .dokter-meta-row label { display: inline-block; width: 150px; font-weight: bold; vertical-align: top; }
        .dokter-meta-row input, .dokter-meta-row textarea { width: 70%; padding: 5px; }
        .dokter-meta-row input[type="checkbox"] { width: auto; margin-top: 5px; }
        .dokter-meta-row textarea { height: 80px; }
    </style>
    <div class="dokter-meta-row">
        <label>📍 Spesialis</label>
        <input type="text" name="dokter_spesialis" value="<?php echo esc_attr( $spesialis ); ?>" placeholder="contoh: Dokter Ortopedi" />
    </div>
    <div class="dokter-meta-row">
        <label>🏥 Lokasi (Kota)</label>
        <input type="text" name="dokter_lokasi" value="<?php echo esc_attr( $lokasi ); ?>" placeholder="contoh: Cirebon, Kuningan, Majalengka" />
    </div>
    <div class="dokter-meta-row">
        <label>📅 Rumah Sakit / Mitra</label>
        <input type="text" name="dokter_rumah_sakit" value="<?php echo esc_attr( $rumah_sakit ); ?>" placeholder="contoh: Mitra Plumbon Cirebon" />
    </div>
    <div class="dokter-meta-row">
        <label>⭐ Rating (Persen)</label>
        <input type="number" step="1" name="dokter_rating_persen" value="<?php echo esc_attr( $rating_persen ); ?>" placeholder="98" /> %
    </div>
    <div class="dokter-meta-row">
        <label>🔥 Paling Dicari</label>
        <input type="checkbox" name="dokter_paling_dicari" value="1" <?php checked( $paling_dicari, 1 ); ?> /> (centang jika iya)
    </div>
    <div class="dokter-meta-row">
        <label>📋 Profil Singkat</label>
        <textarea name="dokter_profil_singkat" placeholder="contoh: Dokter spesialis ortopedi yang berpengalaman lebih dari 10 tahun."><?php echo esc_textarea( $profil_singkat ); ?></textarea>
        <small>Deskripsi singkat tentang dokter (1-2 kalimat)</small>
    </div>
    <?php
}

// 3. Simpan data meta
add_action( 'save_post', 'dokter_save_meta' );
function dokter_save_meta( $post_id ) {
    if ( ! isset( $_POST['dokter_meta_nonce'] ) || ! wp_verify_nonce( $_POST['dokter_meta_nonce'], 'dokter_save_meta' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    if ( get_post_type( $post_id ) != 'dokter' ) return;

    $fields = array(
        'dokter_spesialis', 'dokter_lokasi', 'dokter_rumah_sakit', 
        'dokter_rating_persen', 'dokter_profil_singkat'
    );
    foreach ( $fields as $field ) {
        if ( isset( $_POST[$field] ) ) {
            update_post_meta( $post_id, '_' . $field, sanitize_textarea_field( $_POST[$field] ) );
        }
    }
    $paling_dicari = isset( $_POST['dokter_paling_dicari'] ) ? 1 : 0;
    update_post_meta( $post_id, '_dokter_paling_dicari', $paling_dicari );
}

// 4. Shortcode untuk menampilkan daftar dokter
add_shortcode( 'daftar_dokter', 'tampilkan_daftar_dokter' );
function tampilkan_daftar_dokter() {
    // Ambil nilai filter dan pencarian
    $selected_spesialis = isset( $_GET['spesialis'] ) ? sanitize_text_field( $_GET['spesialis'] ) : '';
    $selected_lokasi    = isset( $_GET['lokasi'] ) ? sanitize_text_field( $_GET['lokasi'] ) : '';
    $search_keyword     = isset( $_GET['cari_dokter'] ) ? sanitize_text_field( $_GET['cari_dokter'] ) : '';
    
    // Query untuk mendapatkan semua dokter
    $all_dokter_args = array(
        'post_type'      => 'dokter',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    );
    $all_dokter_query = new WP_Query( $all_dokter_args );
    
    $spesialis_list = array();
    $lokasi_list = array();
    
    if ( $all_dokter_query->have_posts() ) {
        while ( $all_dokter_query->have_posts() ) {
            $all_dokter_query->the_post();
            $spesialis = get_post_meta( get_the_ID(), '_dokter_spesialis', true );
            $lokasi    = get_post_meta( get_the_ID(), '_dokter_lokasi', true );
            
            if ( $spesialis && ! in_array( $spesialis, $spesialis_list ) ) {
                $spesialis_list[] = $spesialis;
            }
            if ( $lokasi && ! in_array( $lokasi, $lokasi_list ) ) {
                $lokasi_list[] = $lokasi;
            }
        }
    }
    wp_reset_postdata();
    
    sort($spesialis_list);
    sort($lokasi_list);
    
    // Query utama dengan filter
    $args = array(
        'post_type'      => 'dokter',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
    
    $meta_queries = array();
    
    if ( ! empty( $selected_spesialis ) ) {
        $meta_queries[] = array(
            'key'   => '_dokter_spesialis',
            'value' => $selected_spesialis,
        );
    }
    
    if ( ! empty( $selected_lokasi ) ) {
        $meta_queries[] = array(
            'key'   => '_dokter_lokasi',
            'value' => $selected_lokasi,
        );
    }
    
    if ( count( $meta_queries ) > 1 ) {
        $args['meta_query'] = array(
            'relation' => 'AND',
            $meta_queries
        );
    } elseif ( count( $meta_queries ) == 1 ) {
        $args['meta_query'] = $meta_queries;
    }
    
    if ( ! empty( $search_keyword ) ) {
        $args['s'] = $search_keyword;
    }
    
    $dokter_query = new WP_Query( $args );
    
    ob_start();
    ?>
    <div class="dokter-filter-container">
        <!-- Filter Bar Premium -->
        <div class="filter-card">
            <div class="filter-header">
                <div class="filter-title">
                    <span class="filter-icon">🔍</span> Cari Dokter
                </div>
                <button type="button" class="filter-toggle-btn" id="filterToggleBtn">
                    <span class="toggle-icon">▼</span> Filter
                </button>
            </div>
            
            <form method="get" class="filter-form" id="filterForm" action="">
                <div class="filter-body">
                    <div class="filter-group">
                        <label class="filter-label">
                            <span class="label-icon">🔍</span> Nama Dokter
                        </label>
                        <input type="text" name="cari_dokter" placeholder="Cari nama dokter..." value="<?php echo esc_attr( $search_keyword ); ?>" class="filter-input">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">
                            <span class="label-icon">📌</span> Spesialis
                        </label>
                        <select name="spesialis" class="filter-select">
                            <option value="">Semua Spesialis</option>
                            <?php foreach ( $spesialis_list as $spesialis ) : ?>
                                <option value="<?php echo esc_attr( $spesialis ); ?>" <?php selected( $selected_spesialis, $spesialis ); ?>>
                                    <?php echo esc_html( $spesialis ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">
                            <span class="label-icon">📍</span> Lokasi
                        </label>
                        <select name="lokasi" class="filter-select">
                            <option value="">Semua Lokasi</option>
                            <?php foreach ( $lokasi_list as $lokasi ) : ?>
                                <option value="<?php echo esc_attr( $lokasi ); ?>" <?php selected( $selected_lokasi, $lokasi ); ?>>
                                    <?php echo esc_html( $lokasi ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn-apply">
                        <span>✓</span> Terapkan Filter
                    </button>
                    <?php if ( ! empty( $selected_spesialis ) || ! empty( $selected_lokasi ) || ! empty( $search_keyword ) ) : ?>
                        <a href="<?php echo get_permalink(); ?>" class="btn-reset">
                            <span>✗</span> Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Hasil Pencarian & Info -->
        <div class="result-info">
            <div class="result-count">
                <span class="count-number"><?php echo $dokter_query->found_posts; ?></span> Dokter ditemukan
            </div>
            <?php if ( ! empty( $search_keyword ) || ! empty( $selected_spesialis ) || ! empty( $selected_lokasi ) ) : ?>
                <div class="active-filters">
                    <?php if ( ! empty( $search_keyword ) ) : ?>
                        <span class="filter-badge">
                            🔍 <?php echo esc_html( $search_keyword ); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ( ! empty( $selected_spesialis ) ) : ?>
                        <span class="filter-badge">
                            📌 <?php echo esc_html( $selected_spesialis ); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ( ! empty( $selected_lokasi ) ) : ?>
                        <span class="filter-badge">
                            📍 <?php echo esc_html( $selected_lokasi ); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Daftar Dokter -->
        <div class="dokter-grid">
            <?php if ( $dokter_query->have_posts() ) : ?>
                <?php while ( $dokter_query->have_posts() ) : $dokter_query->the_post();
                    $id = get_the_ID();
                    $nama = get_the_title();
                    $foto = get_the_post_thumbnail_url( $id, 'medium' );
                    if ( ! $foto ) $foto = 'https://via.placeholder.com/150?text=No+Photo';
                    
                    $spesialis      = get_post_meta( $id, '_dokter_spesialis', true );
                    $lokasi         = get_post_meta( $id, '_dokter_lokasi', true );
                    $rumah_sakit    = get_post_meta( $id, '_dokter_rumah_sakit', true );
                    $rating_persen  = get_post_meta( $id, '_dokter_rating_persen', true );
                    $paling_dicari  = get_post_meta( $id, '_dokter_paling_dicari', true );
                    $profil_singkat = get_post_meta( $id, '_dokter_profil_singkat', true );
                    
                    $rating_display = ! empty( $rating_persen ) ? $rating_persen : '0';
                    ?>
                    <div class="dokter-card">
                        <div class="card-left">
                            <img src="<?php echo esc_url( $foto ); ?>" alt="Foto <?php echo esc_attr( $nama ); ?>" loading="lazy">
                        </div>
                        <div class="card-right">
                            <h3><?php echo esc_html( $nama ); ?></h3>
                            <div class="spesialis">📍 <?php echo esc_html( $spesialis ); ?></div>
                            <div class="rs">🏥 <?php echo esc_html( $rumah_sakit ); ?>, <?php echo esc_html( $lokasi ); ?></div>
                            
                            <?php if ( ! empty( $profil_singkat ) ) : ?>
                                <div class="profil-singkat">📋 <?php echo esc_html( $profil_singkat ); ?></div>
                            <?php endif; ?>
                            
                            <?php if ( $paling_dicari ) : ?>
                                <div class="label-populer">⭐ Paling Dicari</div>
                            <?php endif; ?>
                            
                            <div class="rating">
                                <div class="stars">
                                    <?php 
                                    $star_rating = round($rating_display / 20);
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $star_rating) {
                                            echo '★';
                                        } else {
                                            echo '☆';
                                        }
                                    }
                                    ?>
                                </div>
                                <span><?php echo esc_html( $rating_display ); ?>% positif</span>
                            </div>
                            
                            <a href="https://mitraplumbon.com/jadwaldokter/" class="btn-buat-janji" target="_blank">
                                Buat Janji →
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else : ?>
                <div class="no-results">
                    <div class="no-results-icon">😞</div>
                    <h4>Tidak ada dokter ditemukan</h4>
                    <p>Coba ubah kata kunci atau filter yang dipilih</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        /* RESET & BASE */
        .dokter-filter-container * {
            box-sizing: border-box;
        }
        
        .dokter-filter-container {
            max-width: 1100px;
            margin: 0 auto;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            padding: 0 16px;
        }
        
        /* FILTER CARD */
        .filter-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 24px;
            overflow: hidden;
            border: 1px solid #eef2f6;
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #eef2f6;
        }
        
        .filter-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .filter-icon {
            margin-right: 8px;
        }
        
        .filter-toggle-btn {
            display: none;
            background: none;
            border: none;
            font-size: 14px;
            color: #2c7da0;
            font-weight: 500;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 30px;
            transition: 0.2s;
        }
        
        .filter-toggle-btn:hover {
            background: #eef2ff;
        }
        
        .filter-body {
            display: flex;
            gap: 20px;
            padding: 20px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 180px;
        }
        
        .filter-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }
        
        .label-icon {
            margin-right: 4px;
        }
        
        .filter-input,
        .filter-select {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            font-size: 14px;
            transition: all 0.2s;
            background: white;
            font-family: inherit;
        }
        
        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: #2c7da0;
            box-shadow: 0 0 0 3px rgba(44,125,160,0.1);
        }
        
        .filter-actions {
            padding: 0 20px 20px 20px;
            display: flex;
            gap: 12px;
        }
        
        .btn-apply,
        .btn-reset {
            padding: 12px 24px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-apply {
            background: #2c7da0;
            color: white;
        }
        
        .btn-apply:hover {
            background: #1f5e7a;
            transform: translateY(-1px);
        }
        
        .btn-reset {
            background: #f1f5f9;
            color: #475569;
        }
        
        .btn-reset:hover {
            background: #e2e8f0;
        }
        
        /* RESULT INFO */
        .result-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            padding: 12px 0;
        }
        
        .result-count {
            font-size: 14px;
            color: #475569;
        }
        
        .count-number {
            font-size: 24px;
            font-weight: bold;
            color: #2c7da0;
        }
        
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .filter-badge {
            background: #eef2ff;
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 12px;
            color: #2c7da0;
            font-weight: 500;
        }
        
        /* DOKTOR GRID */
        .dokter-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        /* DOKTOR CARD */
        .dokter-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 20px;
            display: flex;
            gap: 20px;
            transition: 0.3s;
            border: 1px solid #eef2f6;
        }
        
        .dokter-card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .card-left img {
            width: 120px;
            height: 120px;
            border-radius: 20px;
            object-fit: cover;
            background: #f1f5f9;
        }
        
        .card-right {
            flex: 1;
        }
        
        .card-right h3 {
            font-size: 1.35rem;
            margin: 0 0 8px 0;
            color: #0f172a;
        }
        
        .spesialis,
        .rs {
            margin: 6px 0;
            color: #475569;
            font-size: 0.9rem;
        }
        
        .profil-singkat {
            margin: 10px 0;
            padding: 10px 14px;
            background: #f8fafc;
            border-radius: 14px;
            font-size: 0.85rem;
            color: #334155;
            line-height: 1.45;
            border-left: 4px solid #2c7da0;
        }
        
        .label-populer {
            background: linear-gradient(135deg, #fff3e0, #ffe8cc);
            color: #e67e22;
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            margin: 8px 0;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .rating {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 8px 0;
            flex-wrap: wrap;
        }
        
        .stars {
            color: #fbbf24;
            font-size: 14px;
            letter-spacing: 2px;
        }
        
        .rating span {
            font-weight: 600;
            color: #f39c12;
            font-size: 0.85rem;
        }
        
        .btn-buat-janji {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #2c7da0;
            color: white;
            padding: 10px 24px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            margin-top: 12px;
            transition: 0.2s;
        }
        
        .btn-buat-janji:hover {
            background: #1f5e7a;
            gap: 12px;
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: #f8fafc;
            border-radius: 24px;
        }
        
        .no-results-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        
        .no-results h4 {
            font-size: 1.2rem;
            margin: 0 0 8px 0;
            color: #1e293b;
        }
        
        .no-results p {
            color: #64748b;
        }
        
        /* MOBILE FRIENDLY */
        @media (max-width: 768px) {
            .dokter-filter-container {
                padding: 0 12px;
            }
            
            .filter-header {
                padding: 12px 16px;
            }
            
            .filter-toggle-btn {
                display: block;
            }
            
            .filter-body {
                display: none;
                padding: 16px;
                flex-direction: column;
            }
            
            .filter-body.show {
                display: flex;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .filter-actions {
                display: none;
                padding: 0 16px 16px 16px;
                flex-direction: column;
            }
            
            .filter-actions.show {
                display: flex;
            }
            
            .btn-apply, .btn-reset {
                justify-content: center;
                width: 100%;
            }
            
            .result-info {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .dokter-card {
                flex-direction: column;
                text-align: center;
                padding: 16px;
            }
            
            .card-left img {
                margin: 0 auto;
                width: 100px;
                height: 100px;
            }
            
            .rating {
                justify-content: center;
            }
            
            .profil-singkat {
                text-align: left;
            }
            
            .btn-buat-janji {
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .card-right h3 {
                font-size: 1.2rem;
            }
            
            .filter-title {
                font-size: 16px;
            }
        }
    </style>

    <script>
        (function() {
            var toggleBtn = document.getElementById('filterToggleBtn');
            var filterBody = document.querySelector('.filter-body');
            var filterActions = document.querySelector('.filter-actions');
            
            if (toggleBtn && filterBody && filterActions) {
                toggleBtn.addEventListener('click', function() {
                    filterBody.classList.toggle('show');
                    filterActions.classList.toggle('show');
                    var icon = toggleBtn.querySelector('.toggle-icon');
                    if (icon) {
                        if (filterBody.classList.contains('show')) {
                            icon.innerHTML = '▲';
                            toggleBtn.innerHTML = '<span class="toggle-icon">▲</span> Sembunyikan Filter';
                        } else {
                            icon.innerHTML = '▼';
                            toggleBtn.innerHTML = '<span class="toggle-icon">▼</span> Filter';
                        }
                    }
                });
            }
        })();
    </script>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}

// 5. Pastikan fitur thumbnail aktif untuk CPT Dokter
add_theme_support( 'post-thumbnails' );