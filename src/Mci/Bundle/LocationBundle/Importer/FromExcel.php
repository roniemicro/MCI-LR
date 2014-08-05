<?php

namespace Mci\Bundle\LocationBundle\Importer;

use Cassandra\Database;
use PHPExcel;
use Symfony\Component\Console\Helper\ProgressHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Filesystem\Filesystem;

class FromExcel extends ContainerAware
{
    /** @var  Database */
    private $_em;

    private $configParam = array();

    /** @var  ProgressHelper */
    private $progressHelper;

    /** @var  OutputInterface */
    private $outputInterface;

    public function setProgressHelper(HelperInterface $progress)
    {
        $this->progressHelper = $progress;

        return $this;
    }

    public function setOutputInterface(OutputInterface $outputInterface)
    {
        $this->outputInterface = $outputInterface;

        return $this;
    }

    public function setConfiguration($options)
    {
        $this->configParam = $options;

    }

    public function importAll()
    {
        $imported = 0;

        $this->makeSureColumnFamilyExistAndClean();
        $data = $this->getDataArray();

        $this->progressHelper->start($this->outputInterface, count($data));

        foreach ($data as $key => $row) {

            $this->progressHelper->advance();

            if (strlen($key) != 10) {
                continue;
            }

            $dataArray = $this->getAssocDataArray($data, $row, $key);

            try {
                $this->insert($dataArray);
                $imported++;
            } catch (\Exception $e) {
                continue;
            }
        }

        $this->progressHelper->finish();
    }

    protected function insert($data)
    {

        $cql = 'INSERT INTO ' . $this->configParam['column_family'] .
            ' ("' . implode('", "', $this->configParam['columns']) . '") ' .
            ' VALUES (' . ':' . implode(", :", $this->configParam['columns']) . ')';

        $default = array_fill_keys($this->configParam['columns'], '');


        foreach ($data as $key => $value) {
            if (isset($default[$key])) {
                $default[$key] = $data[$key];
            }
        }

        try {
            $this->getEntityManager()->query($cql, $default);
        } catch (\Exception $e) {
            echo $cql;
            print_r($default);
            exit;
        }
    }

    /**
     * @return Database
     */
    protected function getEntityManager()
    {
        if (!$this->_em) {
            $this->_em = new Database([$this->configParam['host']], $this->configParam['keyspace']);
            $this->_em->connect();
        }

        return $this->_em;
    }

    private function creteColumnFamily()
    {
        $arr = $this->configParam['columns'];

        $Cql = 'create table locations ( ' . $this->configParam['columns'][0] . ' varchar PRIMARY KEY, ';

        array_shift($arr);

        $Cql .= implode(' varchar, ', $arr);

        $Cql .= ' varchar)';

        $this->getEntityManager()->query($Cql);
    }

    protected function makeSureColumnFamilyExistAndClean()
    {
        try {
            $this->getEntityManager()->query('TRUNCATE ' . $this->configParam['column_family']);
        } catch (\Exception $e) {
            echo "Creating column family!";
            $this->creteColumnFamily();
        }
    }

    private function getDataArray()
    {
        $phpExcelObject = $this->getPHPExcelObject();

        $sheetData = $phpExcelObject->getActiveSheet()->toArray(NULL, TRUE, FALSE);

        $data = array();

        foreach ($sheetData as $index => $row) {

            if ($index < 1) {
                continue;
            }

            array_walk($row, function (&$item1, $key) {
                if ($key != 5 && $item1 != "") {
                    $item1 = str_pad($item1 . "", 2, "0", STR_PAD_LEFT);
                }
            });

            $data[$this->getGeoCode($row)] = $row;
        }

        return $data;
    }

    private function getGeoCode($row)
    {
        return $row[0] . $row[1] . $row[2] . $row[3] . $row[4];
    }

    /**
     * @return PHPExcel
     */
    private function getPHPExcelObject()
    {
        return $this->container->get('phpexcel')->createPHPExcelObject($this->getLocalFilePath());
    }

    /**
     * @return mixed
     */
    private function getLocalFilePath()
    {
        $cacheDir = sys_get_temp_dir();

        $filePath = $cacheDir . DIRECTORY_SEPARATOR . md5($this->configParam['path']) . ".xlsx";

        $fs = new Filesystem();

        if (!$fs->exists($filePath)) {
            $fs->copy($this->configParam['path'], $filePath);
        }

        return $filePath;
    }

    /**
     * @param $data
     * @param $row
     * @param $key
     *
     * @return array
     */
    private function getAssocDataArray($data, $row, $key)
    {
        $pourashavaName = isset($data[$row[0] . $row[1] . $row[2] . $row[3]]) ? $data[$row[0] . $row[1] . $row[2] . $row[3]][5] : "";

        $upazilaName = isset($data[$row[0] . $row[1] . $row[2]]) ? $data[$row[0] . $row[1] . $row[2]][5] : "";
        $dataArray = array();

        $dataArray[$this->configParam['columns'][0]] = $key;

        $dataArray[$this->configParam['columns'][1]] = $row[0];
        $dataArray[$this->configParam['columns'][2]] = $data[$row[0]][5];

        $dataArray[$this->configParam['columns'][3]] = $row[1];
        $dataArray[$this->configParam['columns'][4]] = $data[$row[0] . $row[1]][5];

        $dataArray[$this->configParam['columns'][5]] = $row[2];
        $dataArray[$this->configParam['columns'][6]] = $upazilaName;

        $dataArray[$this->configParam['columns'][7]] = $row[3];
        $dataArray[$this->configParam['columns'][8]] = $pourashavaName;

        $dataArray[$this->configParam['columns'][9]] = $row[4];
        $dataArray[$this->configParam['columns'][10]] = $row[5];

        return $dataArray;
    }

}