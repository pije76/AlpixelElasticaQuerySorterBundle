<?php

namespace Alpixel\Bundle\ElasticaQuerySorterBundle\Services;

use Elastica\Query;
use FOS\ElasticaBundle\Repository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Class used in order to sort and paginate an Abstract Query
 * obtained from an elastic search repository
 * Data is stored in session to remember user choice.
 */
class ElasticaQuerySorter
{
    protected $session;
    protected $request;
    protected $sessionData;
    protected $configuration;

    const NO_LIMIT = 99999;
    const SESSION_QUERY_SORTER = 'alpixel_elastica_query_sorter';

    public function __construct(RequestStack $requestStack, Session $session, $configuration)
    {
        $this->request = $requestStack->getCurrentRequest();
        if (empty($this->request)) {
            return;
        }

        $this->session = $session;
        $this->configuration['item_per_page'] = $configuration;

        if (isset($this->request->query) && $this->request->query->has('clear_sort') === true) {
            $this->session->remove(self::SESSION_QUERY_SORTER);
        }

        //Initializing the data in session
        if (!$this->session->has(self::SESSION_QUERY_SORTER)) {
            $this->sessionData = [];
        } else {
            $this->sessionData = $this->session->get(self::SESSION_QUERY_SORTER);
        }
    }

    public function sort(Repository $repository, Query $query, $nbPerPage = null, $defaultSort = [])
    {
        if ($nbPerPage === null) {
            $nbPerPage = $this->getItemPerPage();
        }

        //Creating the main elastica query
        $query->setFields(['_id']);

        //Analysing the request and the session data to add sorting
        $this->addSort($query, $defaultSort);

        //Creating the paginator with the given repository
        $paginator = $repository->findPaginated($query);
        //If this a new sortBy, then we reset the currentPage to 1
        $paginator->setCurrentPage($this->getCurrentPage());

        $paginator->setMaxPerPage($nbPerPage);

        return $paginator;
    }

    protected function getCurrentPage()
    {
        $page = 1;

        if (!empty($this->request) && $this->request->getRealMethod() === 'GET') {
            $page = $this->getPage();
        }

        return $page;
    }

    protected function getPage()
    {
        $nbPage = null;
        if ($this->request->query->has('page')) {
            $nbPage = $this->request->query->get('page');
        }

        if (empty($nbPage) || !is_numeric($nbPage)) {
            return 1;
        }

        return $nbPage;
    }

    protected function addSort(Query &$query, $defaultSort = null)
    {
        $sortBy = explode('-', $this->fetchData('sortBy'));
        $sortOrder = $this->fetchData('sortOrder');

        if ((empty($sortBy) || empty($sortBy[0])) && !empty($defaultSort) && !empty($defaultSort['sortBy'])) {
            $sortBy = [$defaultSort['sortBy']];
            if (!empty($defaultSort['sortOrder'])) {
                $sortOrder = $defaultSort['sortOrder'];
            } else {
                $sortOrder = 'asc';
            }
        }

        $sort = [];
        foreach ($sortBy as $element) {
            if (empty($element) === false && empty($sortOrder) === false) {
                $sort[$element] = [
                    'order'   => strtolower($sortOrder),
                    'missing' => '_last',
                ];
            }
        }

        if (!empty($sort)) {
            $query->setSort($sort);
        }

        return $query;
    }

    public function fetchData($key)
    {
        if (empty($this->request)) {
            return;
        }

        $pageKey = $this->request->getPathInfo();
        $query = $this->request->query;

        if ($query === null) {
            return;
        }

        //Analyzing the session object to see if there is data in it
        //If data is given from Request, it will be override the session data
        if (array_key_exists($pageKey, $this->sessionData) &&
            !$query->has($key) &&
            isset($this->sessionData[$pageKey][$key])
        ) {
            return $this->sessionData[$pageKey][$key];
        }

        if ($query->has('sortBy')) {
            $value = $query->get($key);
            $this->sessionData[$pageKey][$key] = $value;
            $this->storeSessionData();

            return $value;
        }
    }

    public function storeSessionData()
    {
        $this->session->set(self::SESSION_QUERY_SORTER, $this->sessionData);
    }

    /**
     * Gets the value of session.
     *
     * @return mixed
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * Gets the value of request.
     *
     * @return mixed
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Gets the value of sessionData.
     *
     * @return mixed
     */
    public function getSessionData()
    {
        return $this->sessionData;
    }

    public function getItemPerPage()
    {
        return $this->configuration['item_per_page'];
    }
}
