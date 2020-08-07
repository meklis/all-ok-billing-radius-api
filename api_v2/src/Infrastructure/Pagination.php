<?php


namespace Api\Infrastructure;


class Pagination
{
    protected $data;
    protected $recordsPerPage = 50;
    function __construct(&$data, $recordsPerPage = 50)
    {
        $this->recordsPerPage = $recordsPerPage;
        $this->data = $data;
    }
    function setRecordsPerPage($count) {
        $this->recordsPerPage = $count;
    }
    function getTotalPages() {
        $maxPages = 1;
        if(count($this->data) > 0)
            $maxPages = ceil(((count($this->data) - 1) / $this->recordsPerPage));
        return $maxPages;
    }
    function getTotalRecords() {
        return count($this->data);
    }
    function getPage($page_num = 1) {
        $offset = ($page_num - 1) * $this->recordsPerPage;
        return array_slice($this->data, $offset, $this->recordsPerPage);
    }
    function getRecordsCountByPage($page_num = 1) {
        $offset = ($page_num - 1) * $this->recordsPerPage;
        return count(array_slice($this->data, $offset, $this->recordsPerPage));
    }
}