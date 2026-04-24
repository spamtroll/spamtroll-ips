<?php
/**
 * Spamtroll Anti-Spam — CLI installer for IPS Community Suite.
 *
 * Works on a forum with Developer Mode enabled (IN_DEV=1 in conf_global.php).
 * Idempotent: safe to re-run.
 *
 * Usage (from the IPS root directory, where init.php lives):
 *   php applications/spamtroll/setup/cli-install.php
 *
 * What it does (mirrors what the ACP Developer Center would do):
 *   1. Registers the application in core_applications.
 *   2. Registers admin modules from data/modules.json.
 *   3. Runs setup/install.php step1 — creates spamtroll_logs + default settings.
 *   4. Registers hooks from data/hooks.json.
 *   5. Registers the cleanup task from data/tasks.json.
 *   6. Registers widgets from data/widgets.json.
 *   7. Imports language strings from dev/lang.php + dev/jslang.php.
 *   8. Imports templates from dev/html/ into core_theme_templates.
 *   9. Clears caches, purges datastore, compiles templates.
 */

if ( PHP_SAPI !== 'cli' )
{
    fwrite( STDERR, "This script must be run from the CLI.\n" );
    exit( 1 );
}

if ( ! file_exists( 'init.php' ) )
{
    fwrite( STDERR, "Run from the IPS root directory (where init.php lives).\n" );
    exit( 1 );
}

require 'init.php';

$appDir  = 'spamtroll';
$appPath = \IPS\ROOT_PATH . "/applications/$appDir";

if ( ! is_dir( $appPath ) )
{
    fwrite( STDERR, "Application folder not found: $appPath\n" );
    exit( 1 );
}

if ( ! file_exists( "$appPath/vendor/autoload.php" ) )
{
    fwrite( STDERR, "Composer dependencies missing. Run:\n    cd $appPath && composer install --no-dev\nbefore installing.\n" );
    exit( 1 );
}

/* 1. core_applications */
$appJson = json_decode( file_get_contents( "$appPath/data/application.json" ), true );
try
{
    $existingId = \IPS\Db::i()->select( 'app_id', 'core_applications', array( 'app_directory=?', $appDir ) )->first();
    echo "[=] app in core_applications (id=$existingId)\n";
}
catch ( UnderflowException $e )
{
    $maxPos = (int) \IPS\Db::i()->select( 'MAX(app_position)', 'core_applications' )->first();
    \IPS\Db::i()->insert( 'core_applications', array(
        'app_author'       => $appJson['app_author'],
        'app_directory'    => $appJson['app_directory'],
        'app_protected'    => 0,
        'app_enabled'      => 1,
        'app_position'     => $maxPos + 1,
        'app_version'      => '1.0.0',
        'app_long_version' => 10000,
        'app_update_check' => $appJson['app_update_check'] ?? '',
        'app_website'      => $appJson['app_website'] ?? '',
        'app_hide_tab'     => 0,
    ) );
    echo "[+] app inserted in core_applications\n";
}

/* 2. core_modules */
$modules = json_decode( file_get_contents( "$appPath/data/modules.json" ), true );
foreach ( $modules as $area => $areaModules )
{
    foreach ( $areaModules as $key => $module )
    {
        try
        {
            \IPS\Db::i()->select( 'sys_module_id', 'core_modules',
                array( 'sys_module_application=? AND sys_module_area=? AND sys_module_key=?', $appDir, $area, $key )
            )->first();
            echo "[=] module $area/$key\n";
        }
        catch ( UnderflowException $e )
        {
            \IPS\Db::i()->insert( 'core_modules', array(
                'sys_module_key'                => $key,
                'sys_module_application'        => $appDir,
                'sys_module_area'               => $area,
                'sys_module_protected'          => (int) ( $module['protected'] ?? 0 ),
                'sys_module_visible'            => 1,
                'sys_module_position'           => 1,
                'sys_module_default_controller' => $module['default_controller'] ?? '',
            ) );
            echo "[+] module $area/$key inserted\n";
        }
    }
}

/* 3. setup/install.php step1 — creates spamtroll_logs + default settings */
require_once "$appPath/setup/install.php";
$installClass = "\\IPS\\${appDir}\\setup\\install\\_Install";
( new $installClass() )->step1( array() );
echo "[+] install step1 executed (table + default settings)\n";

/* 4. core_hooks — sync from data/hooks.json.
 *     Remove any app rows that aren't in the JSON (orphans from old hooks
 *     that have since been renamed/deleted), insert missing ones.
 */
$hooks = json_decode( file_get_contents( "$appPath/data/hooks.json" ), true );
$expectedFilenames = array_keys( $hooks );

$orphans = iterator_to_array( \IPS\Db::i()->select(
    'id, filename', 'core_hooks',
    array( 'app=? AND ' . \IPS\Db::i()->in( 'filename', $expectedFilenames, true ), $appDir )
) );
foreach ( $orphans as $o )
{
    \IPS\Db::i()->delete( 'core_hooks', array( 'id=?', $o['id'] ) );
    echo "[-] hook {$o['filename']} removed (not in hooks.json)\n";
}

foreach ( $hooks as $filename => $hook )
{
    try
    {
        \IPS\Db::i()->select( 'id', 'core_hooks', array( 'filename=? AND app=?', $filename, $appDir ) )->first();
        echo "[=] hook $filename\n";
    }
    catch ( UnderflowException $e )
    {
        \IPS\Db::i()->insert( 'core_hooks', array(
            'type'     => $hook['type'],
            'class'    => $hook['class'],
            'filename' => $filename,
            'app'      => $appDir,
            'plugin'   => 0,
        ) );
        echo "[+] hook $filename inserted\n";
    }
}

/* 5. core_tasks — IPS 4.7 columns: key/app/frequency/next_run/plugin/enabled */
if ( file_exists( "$appPath/data/tasks.json" ) )
{
    $tasks = json_decode( file_get_contents( "$appPath/data/tasks.json" ), true );
    foreach ( $tasks as $taskKey => $taskVal )
    {
        $frequency = is_array( $taskVal ) ? ( $taskVal['frequency'] ?? 'P1D' ) : $taskVal;
        try
        {
            \IPS\Db::i()->select( 'id', 'core_tasks', array( '`key`=? AND app=?', $taskKey, $appDir ) )->first();
            echo "[=] task $taskKey\n";
        }
        catch ( UnderflowException $e )
        {
            \IPS\Db::i()->insert( 'core_tasks', array(
                'key'       => $taskKey,
                'app'       => $appDir,
                'frequency' => $frequency,
                'next_run'  => time(),
                'plugin'    => null,
                'enabled'   => 1,
            ) );
            echo "[+] task $taskKey inserted (frequency=$frequency)\n";
        }
    }
}

/* 6. core_widgets */
if ( file_exists( "$appPath/data/widgets.json" ) )
{
    $widgets = json_decode( file_get_contents( "$appPath/data/widgets.json" ), true );
    foreach ( $widgets as $widgetKey => $widget )
    {
        try
        {
            \IPS\Db::i()->select( 'id', 'core_widgets', array( '`key`=? AND app=?', $widgetKey, $appDir ) )->first();
            echo "[=] widget $widgetKey\n";
        }
        catch ( UnderflowException $e )
        {
            \IPS\Db::i()->insert( 'core_widgets', array(
                'app'          => $appDir,
                'key'          => $widgetKey,
                'class'        => $widget['class'] ?? '',
                'restrict'     => json_encode( $widget['restrict'] ?? array() ),
                'default_area' => $widget['default_area'] ?? 'sidebar',
                'allow_reuse'  => (int) ( $widget['allow_reuse'] ?? 0 ),
                'menu_style'   => 'menu',
                'embeddable'   => 0,
            ) );
            echo "[+] widget $widgetKey inserted\n";
        }
    }
}

/* 7. language strings (dev/lang.php + dev/jslang.php for every installed language) */
$lang   = array();
$jslang = array();
if ( file_exists( "$appPath/dev/lang.php" ) )   { include "$appPath/dev/lang.php"; }
if ( file_exists( "$appPath/dev/jslang.php" ) ) { include "$appPath/dev/jslang.php"; }

$installedLangs = iterator_to_array( \IPS\Db::i()->select( 'lang_id', 'core_sys_lang' ) );
$langImported = 0;
foreach ( $installedLangs as $langId )
{
    foreach ( array( array( $lang, 0 ), array( $jslang, 1 ) ) as $pair )
    {
        list( $map, $isJs ) = $pair;
        foreach ( $map as $key => $val )
        {
            try
            {
                \IPS\Db::i()->select( 'word_id', 'core_sys_lang_words',
                    array( 'word_app=? AND word_key=? AND lang_id=? AND word_js=?', $appDir, $key, $langId, $isJs )
                )->first();
            }
            catch ( UnderflowException $e )
            {
                \IPS\Db::i()->insert( 'core_sys_lang_words', array(
                    'lang_id'      => $langId,
                    'word_app'     => $appDir,
                    'word_key'     => $key,
                    'word_default' => $val,
                    'word_custom'  => $val,
                    'word_js'      => $isJs,
                    'word_export'  => 1,
                ) );
                $langImported++;
            }
        }
    }
}
echo "[+] language strings imported: $langImported (across " . count( $installedLangs ) . " language(s))\n";

/* 8. templates (dev/html/<location>/<group>/<name>.phtml → core_theme_templates).
 *     Update-or-insert so that re-running the installer after editing .phtml
 *     files picks up the changes.
 */
$htmlPath = "$appPath/dev/html";
$tplInserted = 0; $tplUpdated = 0;
if ( is_dir( $htmlPath ) )
{
    $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $htmlPath, RecursiveDirectoryIterator::SKIP_DOTS ) );
    foreach ( $it as $file )
    {
        if ( $file->getExtension() !== 'phtml' ) { continue; }
        $rel = str_replace( $htmlPath . '/', '', $file->getPathname() );
        $parts = explode( '/', $rel );
        if ( count( $parts ) < 3 ) { continue; }
        $location = $parts[0];
        $group    = $parts[1];
        $name     = pathinfo( end( $parts ), PATHINFO_FILENAME );
        $raw      = file_get_contents( $file->getPathname() );
        $variables = '';
        $content   = $raw;
        if ( preg_match( '#^<ips:template\s+parameters=(["\'])(.*?)\1[^>]*/?>\s*#s', $raw, $m ) )
        {
            $variables = $m[2];
            $content   = preg_replace( '#^<ips:template\s+parameters=(["\']).*?\1[^>]*/?>\s*#s', '', $raw );
        }

        try
        {
            $tplId = \IPS\Db::i()->select( 'template_id', 'core_theme_templates', array(
                'template_app=? AND template_location=? AND template_group=? AND template_name=? AND template_set_id=0',
                $appDir, $location, $group, $name
            ) )->first();
            \IPS\Db::i()->update( 'core_theme_templates', array(
                'template_content' => $content,
                'template_data'    => $variables,
                'template_updated' => time(),
            ), array( 'template_id=?', $tplId ) );
            $tplUpdated++;
        }
        catch ( UnderflowException $e )
        {
            \IPS\Theme::addTemplate( array(
                'app'       => $appDir,
                'location'  => $location,
                'group'     => $group,
                'name'      => $name,
                'variables' => $variables,
                'content'   => $content,
            ) );
            $tplInserted++;
        }
    }
}
echo "[+] templates: inserted=$tplInserted, updated=$tplUpdated\n";

/* 8b. CSS (dev/css/<location>/[<subpath>/]<name>.css → core_theme_css).
 *     We keep the entries up-to-date on every run: if they exist we update
 *     css_content and css_updated; otherwise we insert. This way edits to
 *     dev/css/*.css are picked up by re-running the installer.
 */
$cssPath = "$appPath/dev/css";
$cssUpdated = 0; $cssInserted = 0;
if ( is_dir( $cssPath ) )
{
    $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $cssPath, RecursiveDirectoryIterator::SKIP_DOTS ) );
    foreach ( $it as $file )
    {
        if ( $file->getExtension() !== 'css' ) { continue; }
        $rel = str_replace( $cssPath . '/', '', $file->getPathname() );
        $parts = explode( '/', $rel );
        if ( count( $parts ) < 2 ) { continue; }
        $location = $parts[0];
        $name     = end( $parts );
        $subpath  = count( $parts ) > 2 ? implode( '/', array_slice( $parts, 1, -1 ) ) : '.';
        $content  = file_get_contents( $file->getPathname() );

        try
        {
            $cssId = \IPS\Db::i()->select( 'css_id', 'core_theme_css', array(
                'css_app=? AND css_location=? AND css_path=? AND css_name=? AND css_set_id=0',
                $appDir, $location, $subpath, $name
            ) )->first();
            \IPS\Db::i()->update( 'core_theme_css', array(
                'css_content' => $content,
                'css_updated' => time(),
            ), array( 'css_id=?', $cssId ) );
            $cssUpdated++;
        }
        catch ( UnderflowException $e )
        {
            \IPS\Theme::addCss( array(
                'app'      => $appDir,
                'location' => $location,
                'path'     => $subpath,
                'name'     => $name,
                'content'  => $content,
            ) );
            $cssInserted++;
        }
    }
    \IPS\Db::i()->update( 'core_themes', array( 'set_css_updated' => time() ) );
}
echo "[+] CSS imported: inserted=$cssInserted, updated=$cssUpdated\n";

/* 9. clear caches, purge datastore, compile templates */
\IPS\Db::i()->delete( 'core_store', array( 'store_key LIKE ?', 'template_compiling_%' ) );
\IPS\Data\Store::i()->clearAll();
\IPS\Data\Cache::i()->clearAll();
foreach ( glob( \IPS\ROOT_PATH . '/datastore/*.php' ) as $f ) { @unlink( $f ); }
echo "[+] caches cleared\n";

/* Clear CSS compile locks (in case an earlier run left stale ones) */
\IPS\Db::i()->delete( 'core_store', array( 'store_key LIKE ?', 'css_compiling_%' ) );

/* Compile for the master set AND for every user theme set.
 * The master set (retrieved via Theme::master(), _id=0) is what the ACP
 * actually reads from — css_built_0/*.css. User themes (set_id>=1, via
 * Theme::load($id)) are what the public-facing forum uses — css_built_N/.
 * Theme::load(0) throws OutOfRangeException, hence the separate call.
 */
$themes = array( \IPS\Theme::master() );
foreach ( \IPS\Db::i()->select( 'set_id', 'core_themes' ) as $setId )
{
    try
    {
        $themes[] = \IPS\Theme::load( $setId );
    }
    catch ( \OutOfRangeException $e )
    {
        /* skip — shouldn't happen for rows from core_themes but be safe */
    }
}

$groups = iterator_to_array( \IPS\Db::i()->select(
    'DISTINCT template_location, template_group', 'core_theme_templates', array( 'template_app=?', $appDir )
) );
$cssFiles = iterator_to_array( \IPS\Db::i()->select(
    'DISTINCT css_location, css_path, css_name', 'core_theme_css', array( 'css_app=?', $appDir )
) );

foreach ( $themes as $theme )
{
    foreach ( $groups as $g )
    {
        $theme->compileTemplates( $appDir, $g['template_location'], $g['template_group'] );
    }
    foreach ( $cssFiles as $c )
    {
        $theme->compileCss( $appDir, $c['css_location'], $c['css_path'], $c['css_name'] );
    }
}
echo "[+] templates compiled (" . count( $groups ) . " group(s)) × " . count( $themes ) . " theme set(s)\n";
echo "[+] CSS compiled (" . count( $cssFiles ) . " file(s)) × " . count( $themes ) . " theme set(s)\n";

/* Regenerate plugins/hooks.php so IPS runtime actually loads our hooks.
 * Without this, rows in core_hooks are invisible to the framework —
 * `\IPS\IPS::$hooks` stays empty and no interception happens. */
\IPS\Plugin\Hook::writeDataFile();
echo "[+] plugins/hooks.php regenerated\n";

echo "\n=== INSTALLATION COMPLETE ===\n";
echo "Next steps:\n";
echo "  1. Log into ACP → Community → Spamtroll → Settings\n";
echo "  2. Paste your API key (from https://spamtroll.io) and enable the app\n";
echo "  3. Optionally add bypass groups, tune thresholds\n";
