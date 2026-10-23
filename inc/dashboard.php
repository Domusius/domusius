<?php
/**
 * inc/dashboard.php - Sorterer efter m.dashboard_order (Præcis hvor din admin gemmer!)
 */

$active_widgets = [];
try {
    // VI SORTERER DIREKTE EFTER DASHBOARD_ORDER FRA MODULES TABELLEN
    $stmt = $pdo->query("SELECT * FROM modules 
                         WHERE is_installed = 1 
                         AND is_active = 1 
                         AND show_on_dashboard = 1 
                         ORDER BY dashboard_order ASC, id ASC");
    $active_widgets = $stmt->fetchAll();
} catch (PDOException $e) {
    $active_widgets = [];
}
?>

<style>
    /* Dashboard grid-layout */
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 25px;
        padding: 10px;
    }
    
    /* Widget størrelser */
    .widget-small { grid-column: span 1; }
    .widget-medium { grid-column: span 2; }
    .widget-large { grid-column: span 3; }
    .widget-full { grid-column: 1 / -1; }

    /* Rammen omkring hvert modul */
    .dashboard-widget {
        background: white;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
        margin-bottom: 5px;
    }
    
    /* Overskriften på modulet */
    .widget-header {
        padding: 15px 20px;
        background: #fcfcfc;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .widget-header span {
        font-weight: 800;
        color: #475569;
        font-size: 0.85em;
        letter-spacing: 0.5px;
    }
    
    /* Indholdet indeni modulet */
    .widget-body {
        padding: 20px;
        flex-grow: 1;
    }

    .section-title {
        font-weight: 800;
        padding: 20px 10px;
        color: #1e293b;
        font-size: 1.2em;
    }

    @media (max-width: 768px) {
        .dashboard-grid { grid-template-columns: 1fr; }
        .widget-medium, .widget-large { grid-column: span 1; }
    }
</style>

<div class="dashboard-wrapper">

    <?php if (!empty($active_widgets)): ?>
        <div class="section-title">
            <i class="fas fa-chart-line" style="margin-right: 10px; color: #3b82f6;"></i> Modul Oversigt
        </div>
        
        <div class="dashboard-grid">
            <?php foreach($active_widgets as $mod): 
                $size_class = "widget-" . ($mod['dashboard_size'] ?? 'medium');
                $icon = !empty($mod['module_icon']) ? $mod['module_icon'] : 'fas fa-box';
                $widget_path = "system/modules/" . $mod['module_key'] . "/widgets/dashboard_view.php";
                $display_name = strtoupper(htmlspecialchars($mod['name']));
            ?>
                <div class="dashboard-widget <?= $size_class ?>">
                    <div class="widget-header">
                        <span>
                            <i class="<?= $icon ?>" style="margin-right: 10px; color: #94a3b8;"></i>
                            <?= $display_name ?>
                        </span>
                        <a href="index.php?side=<?= $mod['module_key'] ?>" style="color: #cbd5e1; transition: 0.2s;" onmouseover="this.style.color='#3b82f6'" onmouseout="this.style.color='#cbd5e1'">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                    
                    <div class="widget-body">
                        <?php 
                        if (file_exists($widget_path)) {
                            include $widget_path;
                        } else {
                            echo "<div style='color: #94a3b8; font-style: italic; font-size: 0.85em; text-align:center; padding:10px;'>
                                    <i class='fas fa-exclamation-triangle'></i> Mangler: dashboard_view.php
                                  </div>";
                        }
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
    <?php else: ?>
        <div class="no-modules-notice" style="text-align: center; padding: 60px; background: #ffffff; border-radius: 20px; border: 2px dashed #e2e8f0; margin: 20px;">
            <i class="fas fa-plus-circle" style="font-size: 2.5em; color: #cbd5e1; margin-bottom: 15px;"></i>
            <h2 style="color: #1e293b; font-weight: 800;">Ingen aktive moduler</h2>
            <p style="color: #64748b;">Gå til Modul Styring for at aktivere moduler på dit dashboard.</p>
        </div>
    <?php endif; ?>
</div>