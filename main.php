<?php
require realpath(__DIR__) . '/bootstrap.php';

use lib\Log\Log;

use Phine\Path\Path;
use lib\Data\StringKey;

use lib\PDO\MasterPDO;
use lib\Query\DatabaseQuery;
use lib\PDO\DatabaseInterface;

use lib\Data\DataHandler;
use lib\CSV\CSV;

# Logger instance
$log = new Log($output->get('logs'));

# User Input
$_parameters = array(
    'regpat' => 'Z3418645100',
    'date_m' => 1,
    'date_y' => 2016,
);

$months = array(
    1 =>  ['string'=>'Enero', 'days'=>31,],
    2 =>  ['string'=>'Febrero', 'days'=>28,],
    3 =>  ['string'=>'Marzo', 'days'=>31,],
    4 =>  ['string'=>'Abril', 'days'=>30,],
    5 =>  ['string'=>'Mayo', 'days'=>31,],
    6 =>  ['string'=>'Junio', 'days'=>30,],
    7 =>  ['string'=>'Julio', 'days'=>31,],
    8 =>  ['string'=>'Agosto', 'days'=>31,],
    9 =>  ['string'=>'Septiembre', 'days'=>30,],
    10 => ['string'=>'Octubre', 'days'=>31,],
    11 => ['string'=>'Noviembre', 'days'=>30,],
    12 => ['string'=>'Diciembre', 'days'=>31,],
);

// https://support.microsoft.com/en-us/kb/214019
if ($_parameters['date_y']%4 == 0 && $_parameters['date_y']%100 != 0)
{
    $months[2]['days'] = 29;
    $log->dd(['debug'], "Year {$_parameters['date_y']} is lead.", $months[2]);
}

$log->dd(['debug'], "Start to scan dir `".$output->get('request')."`");




function dd ($string = '', $return = 0) { echo $string . "\t\t\t\t\t"; if ($return) echo "\r"; else echo "\n";}

$master = new MasterPDO(array(
    'hosting' => $settings->get('SQLSRV.database'),
    'username' => $settings->get('SQLSRV.username'),
    'password' => $settings->get('SQLSRV.password'),
));

$log->dd(['debug'], "Creating Databases Querys and Interfaces");
$dbq = new DatabaseQuery();
$dbi = new DatabaseInterface($master, [], $cache, $log);


$log->dd(['debug'], "Creating Data Handlers and CSV Interface");
$dh = new DataHandler();
$csv = new CSV();


$log->dd(['dbs','alert'], "Cache was not found. Researching again on `nomGenerales.db`");
$dbs = [];
$dbs_names = [];
$i = 0;
$rows = $master->using('nomGenerales')
    ->query($dbq->getDatabaseDic())
    ->fetchAll();
foreach ($rows as $row)
{
    if (isset($row[0]) && $row[0] && $master->testConnection($row[0]))
    {
        if ($master->using($row[0]))
        {
            $dbs[] = $row[0];
            $dbs_names [$row[0]] = $row[1];
        }
    } else {
        $i++;
    }
}
$log->dd(['dbs','debug'], "Databases.", ['db_found'=>count($dbs), 'db_lost' => $i]);
$dbi->setDatabases($dbs);
$log->dd(['dbs','debug'], "Databases loaded.", ['dbs_loaded'=>count($dbs)]);



# Methods
$dbi->callback('dic',function ($req, $res) {
    $req['row']['key'] = StringKey::get($req['row'][1]);
    $res[$req['database']][$req['row'][0]] = $req['row'];
    return $res;
});



# DBI Dictionaries
$log->dd(['debug'], "Fetching databases tables and dictionaries.");


# Get Workers and grouping by NSS
$log->dd(['query','debug'], "Executing DB_REGPAT_DIC", ['query'=>$dbq->getRegPatDic()]);
$db_regpat_dic = $dbi->set($dbq->getRegPatDic())
    ->execute(function($req, $res){
        $res[$req['database']][] = $req['row']['cregistroimss'];
        return $res;
    });


$log->dd(['query','debug'], "Executing DB_WORKER_DIC && DB_WORKER_AUSC_DIC", ['db_worker_dic'=>$dbq->getDatabaseWorkerDic(),'db_worker_ausc'=>$dbq->getDatabaseWorkerAusc()]);
$db_workers = array();
$db_workers_ausc = array();
$db_workers_incp = array();
foreach ($dbs as $db_slug)
{
    print_r(array_search($_parameters['regpat'], $db_regpat_dic[$db_slug]));
    if (isset($db_regpat_dic[$db_slug]) && array_search($_parameters['regpat'], $db_regpat_dic[$db_slug]))
    {
        $log->dd(['debug'], $db_slug.' is in regpat');
        $db_workers_q = $master->using($db_slug)->query($dbq->getDatabaseWorkerDic());
        $db_workers_q->execute();
        $db_workers[$db_slug] = $db_workers_q->fetchAll();

        $db_workers_ausc_q = $master->using($db_slug)->query($dbq->getDatabaseWorkerAusc());
        $db_workers_ausc_q->execute();
        foreach ($db_workers_ausc_q->fetchAll() as $row)
        {
            $db_workers_ausc[$db_slug][$row['idempleado']] = $row['ausc'];
        }

        $db_workers_incp_q = $master->using($db_slug)->query($dbq->getDatabaseWorkerIncp());
        $db_workers_incp_q->execute();
        foreach ($db_workers_incp_q->fetchAll() as $row)
        {
            $db_workers_incp[$db_slug][$row['idempleado']] = $row['diasautorizados'];
        }


    }
}

/*[AfiliacionNSS] => 14068631432
  [CodigoEmpleado] => 0094
  [NombreTrabajador] => Campos Hernandez Maria De Jesus
  [CURPI] => CAHJ
  [fechanacimiento] => 860205
  [CURPF] => MHGMRS00
  [PrimeraParteRFC] => CAHJ
  [TerceraParteRFC] => K8A
  [Clave] => B */




# Dump
$log->dd(['CSV','debug'], "Ordering csv rows");

$csv->writerow([
    '','','','','','','','',
    'NÓMINAS'
]);

$csv->writerow([
    'Registro Patronal',
    'Afiliacion NSS',
    'Codigo Empleado',
    'Nombre Trabajador',
    'CURP',
    'RFC',
    'Clave',
    'Fecha',

    // BEGIN NOMINAS
        'SBC',
        'Dias Mes',
        'Inc.',
        'Aus.',
        'Dias Cotiz.',
    // END NOMINAS
]);

foreach ($db_workers as $db_slug => $_db_workers)
{
    foreach ($_db_workers as $id => $db_worker)
    {
        $csv->writerow([
            // Registro Patronal
            $_parameters['regpat'],
            // Afiliacion NSS
            $db_worker['AfiliacionNSS'],
            // Codigo Empleado
            $db_worker['CodigoEmpleado'],
            // Nombre Trabajador
            $db_worker['NombreTrabajador'],
            // CURP
            $db_worker['CURPI'].'-'.$db_worker['fechanacimiento'].'-'.$db_worker['CURPF'],
            // RFC
            $db_worker['PrimeraParteRFC'].'-'.$db_worker['fechanacimiento'].'-'.$db_worker['TerceraParteRFC'],
            // Clave
            $db_worker['Clave'],
            // Fecha
            '',

            // BEGIN NOMINAS
                // SBC
                '',
                // Dias Mes
                $months[$_parameters['date_m']]['days'],
                // Inc.
                isset($db_workers_incp[$db_slug][$db_worker['idempleado']])?$db_workers_incp[$db_slug][$db_worker['idempleado']]:0,
                // Aus.
                isset($db_workers_ausc[$db_slug][$db_worker['idempleado']])?$db_workers_ausc[$db_slug][$db_worker['idempleado']]:0,
                // Dias Cotiz.
                ($months[$_parameters['date_m']]['days']) - (isset($db_workers_ausc[$db_slug][$db_worker['idempleado']])?$db_workers_ausc[$db_slug][$db_worker['idempleado']]:0),
            // END NOMINAS
        ]);
    }
}

$log->dd(['debug'], "Starting to write CSV file");
date_default_timezone_set("America/Mexico_City");
$filename = date("Ymd\THis",time()).'_'.StringKey::get($_parameters['regpat']).'.csv';
$_output = Path::join([$output->get('output'), $filename]);
file_put_contents($_output, $csv->get());
$log->dd (['CSV','done'],$_output);