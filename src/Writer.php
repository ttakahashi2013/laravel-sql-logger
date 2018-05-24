<?php

namespace Mnabialek\LaravelSqlLogger;

use Mnabialek\LaravelSqlLogger\Objects\SqlQuery;
use App\Libs\SlackNotification;
use App\Libs\Util;

class Writer
{
    /**
     * @var Formatter
     */
    private $formatter;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var FileName
     */
    private $fileName;

    /**
     * Writer constructor.
     *
     * @param Formatter $formatter
     * @param Config $config
     * @param FileName $fileName
     */
    public function __construct(Formatter $formatter, Config $config, FileName $fileName)
    {
        $this->formatter = $formatter;
        $this->config = $config;
        $this->fileName = $fileName;
    }

    /**
     * Save queries to log.
     *
     * @param SqlQuery $query
     */
    public function save(SqlQuery $query)
    {
        $this->createDirectoryIfNotExists($query->number());

        $line = $this->formatter->getLine($query);

        if ($this->shouldLogQuery($query)) {
            $this->saveLine($line, $this->fileName->getForAllQueries(), $this->shouldOverrideFile($query));
        }

        if ($this->shouldLogSlowQuery($query)) {
            $this->saveLine($line, $this->fileName->getForSlowQueries());
        }
    }

    /**
     * Create directory if it does not exist yet.
     *
     * @param int $queryNumber
     */
    protected function createDirectoryIfNotExists($queryNumber)
    {
        if ($queryNumber == 1 && ! file_exists($directory = $this->directory())) {
            mkdir($directory, 0777, true);
        }
    }

    /**
     * Get directory where file should be located.
     *
     * @return string
     */
    protected function directory()
    {
        return rtrim($this->config->logDirectory(), '\\/');
    }

    /**
     * Verify whether query should be logged.
     *
     * @param SqlQuery $query
     *
     * @return bool
     */
    protected function shouldLogQuery(SqlQuery $query)
    {
        return $this->config->logAllQueries() &&
            preg_match($this->config->allQueriesPattern(), $query->raw());
    }

    /**
     * Verify whether slow query should be logged.
     *
     * @param SqlQuery $query
     *
     * @return bool
     */
    protected function shouldLogSlowQuery(SqlQuery $query)
    {
        //テストコード
        $record['level_name'] = 'slow guery';
        $record['level_name'] = 'テストです';
        $record['message'] = 'スロークエリ';
        $record['context'] = 'context';
        $record['extra'] = 'extra';
        $isAnnounce = true;
        $this->toSlack($record, $isAnnounce);
        return $this->config->logSlowQueries() && $query->time() >= $this->config->slowLogTime() &&
            preg_match($this->config->slowQueriesPattern(), $query->raw());
    }

    /**
     * Save data to log file.
     *
     * @param string $line
     * @param string $fileName
     * @param bool $override
     */
    protected function laravel-sql-logger($line, $fileName, $override = false)
    {
        file_put_contents($this->directory() . DIRECTORY_SEPARATOR . $fileName,
            $line, $override ? 0 : FILE_APPEND);
    }

    /**
     * Verify whether file should be overridden.
     *
     * @param SqlQuery $query
     *
     * @return bool
     */
    private function shouldOverrideFile(SqlQuery $query)
    {
        return ($query->number() == 1 && $this->config->overrideFile());
    }

    private function toSlack($record, $isAnnounce)
    {
        // slackで見やすいように文字数を補正する
        if (isset($record['context']['gitHash'])) {
            $record['context']['gitHash']
                = substr($record['context']['gitHash'], 0, self::GIT_HASH_LENGTH);
        }

        $notification = (new SlackNotification())
            ->setLevel($record['level_name'])
            ->setIsAnnounced($isAnnounce)
            ->setAttachmentTitle($record['message'])
            ->setFields(array_merge($record['context'], $record['extra']));
        Util::toSlack($notification);
    }
}
