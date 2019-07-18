<?php
/**
 * Provides tasks for backing up remote/local databases and importing/exporting backups on local and remote host
 */

namespace Deployer;

require_once 'utils/files.php';
require_once 'utils/localhost.php';

use function \Gaambo\DeployerWordpress\Utils\Files\getRemotePath;

/**
 * Backup remote database and download to localhost
 * Needs the following variables:
 *  - dump_path on localhost: Path in which to store database dumps/backups
 *  - bin/wp: WP CLI binary/command to use (has a default)
 * dump_path on remote host get's set in this task
 * Backup gets deleted in pull task
 * @todo check if this (especially dump_path in shared) works with simple recipe
 */
task('db:remote:backup', function () {
    $localDumpPath = \Gaambo\DeployerWordpress\Utils\Localhost\getLocalhostConfig('dump_path');
    $remotePath = getRemotePath();
    $now = date('Y-m-d_H-i', time());
    set('dump_file', "db_backup-$now.sql");
    set('dump_filepath', get('dump_path') . '/' . get('dump_file'));

    run('mkdir -p ' . get('dump_path'));
    run("cd $remotePath && {{bin/wp}} db export {{dump_filepath}} --add-drop-table");

    runLocally("mkdir -p $localDumpPath");
    download('{{dump_filepath}}', "$localDumpPath/{{dump_file}}");
})->desc('Backup remote database and download');

/**
 * Backup local database and upload to remote host
 * Needs the following variables:
 *  - dump_path on localhost: Path in which to store database dumps/backups
 *  - bin/wp on localhost: WP CLI binary/command to use (has a default)
 * dump_path on remote host get's set in this task
 * Backup gets deleted in push task
 * @todo check if this (especially dump_path in shared) works with simple recipe
 */
task('db:local:backup', function () {
    $localWp = \Gaambo\DeployerWordpress\Utils\Localhost\getLocalhostConfig('bin/wp');
    $localDumpPath = \Gaambo\DeployerWordpress\Utils\Localhost\getLocalhostConfig('dump_path');
    $now = date('Y-m-d_H-i', time());
    set('dump_file', "db_backup-$now.sql");
    set('dump_filepath', '{{dump_path}}/{{dump_file}}');

    runLocally("mkdir -p $localDumpPath");
    runLocally("$localWp db export $localDumpPath/{{dump_file}} --add-drop-table");

    run('mkdir -p {{dump_path}}');
    upload(
        "$localDumpPath/{{dump_file}}",
        '{{dump_filepath}}'
    );
})->desc('Backup local database and upload');

/**
 * Import current database backup (from localhost) on remote host
 * Needs the following variables:
 *  - bin/wp on remote host: WP CLI binary/command to use (has a default)
 *  - public_url on localhost an remote host: To replace in database
 * dump_filepath is set in db:local:backup task and gets deleted after importing
 */
task('db:remote:import', function () {
    $localUrl = \Gaambo\DeployerWordpress\Utils\Localhost\getLocalhostConfig('public_url');
    $remotePath = getRemotePath();
    run("cd $remotePath && {{bin/wp}} db import {{dump_filepath}}");
    run("cd $remotePath && {{bin/wp}} search-replace $localUrl {{public_url}}");
    run('rm -f {{dump_filepath}}');
})->desc('Imports Database on remote host');

/**
 * Import current database backup (from remote host) on local host
 * Needs the following variables:
 *  - bin/wp on localhost: WP CLI binary/command to use (has a default)
 *  - public_url on localhost an remote host: To replace in database
 *  - dump_path on localhost: Path in which backups are stored
 * dump_filepath is set in db:local:backup task and gets deleted after importing
 */
task('db:local:import', function () {
    $localWp = \Gaambo\DeployerWordpress\Utils\Localhost\getLocalhostConfig('bin/wp');
    $localUrl = \Gaambo\DeployerWordpress\Utils\Localhost\getLocalhostConfig('public_url');
    $localDumpPath = \Gaambo\DeployerWordpress\Utils\Localhost\getLocalhostConfig('dump_path');
    runLocally("$localWp db import $localDumpPath/{{dump_file}}");
    runLocally("$localWp search-replace {{public_url}} $localUrl");
    runLocally("rm -f $localDumpPath/{{dump_file}}");
})->desc('Imports Database on local host');

/**
 * Pushes local database to remote host
 * Runs db:local:backup and db:remote:import tasks in series
 * See tasks definitions for required variables
 */
task('db:push', ['db:local:backup', 'db:remote:import']);

/**
 * Pulls remote database to localhost
 * Runs db:remote:backup and db:local:import tasks in series
 * See tasks definitions for required variables
 */
task('db:pull', ['db:remote:backup', 'db:local:import']);
