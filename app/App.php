<?php

namespace GRGoogleSearchConsole;

use \Google\Client;
use \Google\Service\Webmasters;
use \Google_Service_Webmasters_SearchAnalyticsQueryRequest;
use \Google_Service_Webmasters_ApiDimensionFilter;

class App {
    
    private $_client_id = '';
    
    private $_client_secret = '';
    
    private $_user_id = '';
    
    public function __construct()
    {
        define('GR_GSC_CLIENT_ID', '301536851188-9g9kdf3li57k82bkh06tic450ddjg13r.apps.googleusercontent.com');
        define('GR_GSC_CLIENT_SECRET', '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');
        
        if(!defined('GR_GSC_CLIENT_ID') || !defined('GR_GSC_CLIENT_SECRET')) return;
        $this->_user_id = get_current_user_id();
        $this->_client_id = GR_GSC_CLIENT_ID;
        $this->_client_secret = GR_GSC_CLIENT_SECRET;
        $this->client = new Client();
        $this->client->setClientId($this->_client_id);
        $this->client->setClientSecret($this->_client_secret);
        $this->client->setRedirectUri(admin_url('admin.php?page=search-console-page'));
        $this->client->addScope('https://www.googleapis.com/auth/webmasters.readonly');
        $this->client->setAccessType('offline');
        $this->client->setApprovalPrompt('force');

        add_action('admin_menu', array($this, 'add_plugin_menu'));
        add_action('admin_head', array($this, 'add_inline_styles'));
        add_action('admin_enqueue_scripts', array($this,'enqueue_admin_script'));
        add_action('wp_ajax_get_pages_by_site_url', array($this,'list_pages_and_top_keywords'));
        add_action('wp_ajax_get_more_pages', array($this,'get_more_pages'));
    }

    public function add_plugin_menu()
    {
        add_menu_page(
            'Google Search Console',
            'Google Search Console',
            'manage_network', // for multisite superadmins only
            'search-console-page',
            array($this, 'plugin_page_content'),
            'dashicons-search',
            20
        );
    }
    
    public function enqueue_admin_script()
    {
        wp_enqueue_script('gsc-script', GSC_PLUGIN_URL . '/scripts/main.js', array('jquery'), time(), true);
        wp_localize_script('gsc-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
    }

    public function add_inline_styles()
    {
        ?>
        <style>
            .pages_pagination ul{
                display: flex;
                align-items: center;
                justify-content: space-evenly;
                margin-top: 50px;
            }

            .pages_pagination ul .page_pagination_items{
                border: 1px solid blue;
                box-sizing: border-box;
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .pages_pagination ul .page_pagination_items.active{
                border: 2px solid green;
            }



            #the-list tr:nth-child(5n+1) td{
                border-top:1px solid #c3c4c7;
            }
            .search_block input, .search_properties select {
                width: 100%;
                max-width: 500px;
                height: 37px;
            }
            .search_block{
                margin-top:20px;
            }
            .search_properties, .search_input_block{
                display: flex;
                gap: 6px;
                flex-direction: column;
            }
            .form_block{
                width: 100%;
                max-width: 500px;
                margin:0 auto;
            }
            .search_button_block {
                margin:20px auto;
                display: flex;
                justify-content: center;
            }

        </style>
        <?php
    }
    
    private function storeRefreshToken($refreshToken)
    {
        // store refresh tokens individually based on current user, to be able to show the user's Search Properties from GSC
        update_user_meta($this->_user_id, 'google_search_console_refresh_token', $refreshToken);
    }
    
    private function hasStoredRefreshToken()
    {
        return get_user_meta($this->_user_id, 'google_search_console_refresh_token', true) !== false;
    }
    
    private function getStoredRefreshToken()
    {
        return get_user_meta($this->_user_id, 'google_search_console_refresh_token', true);
    }
    
    private function fetch_token()
    {
        $fetch_account =  $this->client->fetchAccessTokenWithRefreshToken($this->getStoredRefreshToken());
        if(!empty($fetch_account) && !empty($fetch_account['access_token'])){
            return $fetch_account['access_token'];
        }
        return false;
    }
    
    public function plugin_page_content()
    {
        $properties = $this->get_properties(); ?>

         
        <div class="wrap">
            <h2>GR: Google Search Console</h2>
            <?php if (!empty($properties)) { ?>
                 <div class="form_block">
                    <div class="search_properties">
                        <label for="site_url">Select a Property</label>
                        <select required name="site_url" id="site_url">
                            <option value="">Select property</option>
                            <?php foreach ($properties as $property) { ?>
                                <option value="<?= esc_attr($property->siteUrl) ?>"><?= esc_html($property->siteUrl) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="search_block">
                        <div class="search_input_block ">
                            <label for="site_url">Search for page</label>
                            <input type="text" name="" class="form-control search_keyword" >
                        </div>
                        <div class="search_button_block ">
                            <button class="get_pages_button loading-button">Search Pages</button>
                        </div>
                    </div>
                </div>
            <?php } else {
                echo 'No properties found.';
            } ?>
            
            <div class="pages"></div>
        </div>
        <?php 
    }

    private function get_search_console_properties()
    {
        if (isset($_GET['code'])) {
            $this->client->fetchAccessTokenWithAuthCode($_GET['code']);
            $accessToken = $this->client->getAccessToken();
            $this->storeRefreshToken($accessToken['access_token']);
        }
        elseif (!$this->client->getAccessToken() && $this->hasStoredRefreshToken()) {
            $this->client->fetchAccessTokenWithRefreshToken($this->getStoredRefreshToken());
            $this->client->setAccessToken($this->getStoredRefreshToken());
            $accessToken = $this->client->getAccessToken();
        }

        if ($this->client->getAccessToken() && $this->fetch_token()) {
            wp_redirect('/wp-admin/admin.php?page=search-console-page');
            exit;
        }

        wp_redirect($this->client->createAuthUrl());
        exit;
    }
    
    private function get_properties()
    {
        if (!$this->client->getAccessToken() && $this->hasStoredRefreshToken()) {
            $this->client->setAccessToken($this->getStoredRefreshToken());
            $accessToken = $this->client->getAccessToken();
        }

        if ($this->client->getAccessToken() && $this->fetch_token()) {
            $searchConsole = new Webmasters($this->client);
            $properties = $searchConsole->sites->listSites();
            return $properties->siteEntry;
        }
        $this->get_search_console_properties();
    }
    
    public function list_pages_and_top_keywords()
    {
        
        if (!$this->client->getAccessToken() && $this->hasStoredRefreshToken()) {
            $this->client->setAccessToken($this->getStoredRefreshToken());
            $accessToken = $this->client->getAccessToken();
        }

        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-3 months', strtotime($endDate)));
        $siteUrl =  $_POST['site_url'];
        $keyword = $_POST['keyword'] ?? '';
        $pageSize = 30;
        
        if ($this->fetch_token()) {
            $searchConsole = new Webmasters($this->client);
            $pageRequest = new Google_Service_Webmasters_SearchAnalyticsQueryRequest();
            $pageRequest->setStartDate($startDate);
            $pageRequest->setEndDate($endDate);
            $pageRequest->setDimensions(array('page'));
            
            $filterGroup = new \Google_Service_Webmasters_ApiDimensionFilterGroup();
            $filter = new \Google_Service_Webmasters_ApiDimensionFilter();
            $filter->setDimension('page');
            $filter->setExpression($keyword);
            $filter->setOperator('contains');
            $filterGroup->setFilters([$filter]);
            $pageRequest->setDimensionFilterGroups([$filterGroup]);
            
            $pageRequest->setRowLimit($pageSize);
            $pageResults = $searchConsole->searchanalytics->query($siteUrl, $pageRequest);
            $pages = $pageResults->getRows();
            
            $distinctPages = [];
            if ($pages) {
                foreach ($pages as $page) {
                    $pageKeys = $page->getKeys();
                    $distinctPages[] = $pageKeys[0];
                }
            }

            $distinctPages = array_unique($distinctPages);
            $pagesWithQueries = array();
            
            $count = 0;
            $more_pages = [];
            foreach ($distinctPages as $page) {
                $pageUrl = $page;//->getKeys()[0];
                $count++; 
                if ($count > 10) {
                    $more_pages[] = $pageUrl;
                    continue;
                }
                
                $request = new Google_Service_Webmasters_SearchAnalyticsQueryRequest();
                $request->setStartDate($startDate);
                $request->setEndDate($endDate);
                $request->setDimensions(['query']);
                $request->setRowLimit(1000);
                $filter = new \Google_Service_Webmasters_ApiDimensionFilter();
                $filter->setDimension('page');
                $filter->setExpression($pageUrl);
                $filterGroup = new \Google_Service_Webmasters_ApiDimensionFilterGroup();
                $filterGroup->setFilters([$filter]);
                $request->setDimensionFilterGroups([$filterGroup]);
                $query = $searchConsole->searchanalytics->query($siteUrl, $request);
                $queries = $query->getRows();
                usort($queries, function($a, $b) {
                    return $b['impressions'] - $a['impressions'];
                });
    
                $topQueries = array_slice($queries, 0, 5);

                $pagesWithQueries[$count]['pageUrl'] = $pageUrl;
                $pagesWithQueries[$count]['queries'] = $topQueries;
            }

            wp_send_json(['pages' => $pagesWithQueries, 'more_pages' => $more_pages]);
            die;
        }
        wp_send_json(['error' => true]);
        die;
    }
    
    public function get_more_pages()
    {
        
        if (!$this->client->getAccessToken() && $this->hasStoredRefreshToken()) {
            $this->client->setAccessToken($this->getStoredRefreshToken());
            $accessToken = $this->client->getAccessToken();
        }
        
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-3 months', strtotime($endDate)));
        $siteUrl =  $_POST['site_url'];
        $keyword = $_POST['keyword'] ?? '';
        $more_pages = $_POST['more_pages']??[];

        $more_pages_count = $_POST['more_pages_count'];
        
        
        if ($this->fetch_token()) {
            
            $distinctPages = [];
            $new_more_pages = [];
            
            if(count($more_pages) >= 5){
                $count = 0;
                foreach($more_pages as $page){
                    $count++;
                    if ($count <= 5) {
                        $distinctPages[] = $page;
                    }else{
                        $new_more_pages[] = $page;
                    }
                }
            }
            $searchConsole = new Webmasters($this->client);
            
            if(count($distinctPages) < 5){
                
                $new_pages_count = 10 - count($distinctPages);
                $more_pages_count = $more_pages_count+ 30;
               
                $pageRequest = new Google_Service_Webmasters_SearchAnalyticsQueryRequest();
                $pageRequest->setStartDate($startDate);
                $pageRequest->setEndDate($endDate);
                $pageRequest->setDimensions(array('page'));
                $pageRequest->setStartRow($more_pages_count); 
                $filterGroup = new \Google_Service_Webmasters_ApiDimensionFilterGroup();
                $filter = new \Google_Service_Webmasters_ApiDimensionFilter();
                $filter->setDimension('page');
                $filter->setExpression($keyword);
                $filter->setOperator('contains');
                $filterGroup->setFilters([$filter]);
                $pageRequest->setDimensionFilterGroups([$filterGroup]);
                
                $pageRequest->setRowLimit($new_pages_count);
                $pageResults = $searchConsole->searchanalytics->query($siteUrl, $pageRequest);
                $pages = $pageResults->getRows();
                
                if ($pages) {
                    foreach ($pages as $pagen) {
                        $pageKeys = $pagen->getKeys();
                        $distinctPages[] = $pageKeys[0];
                    }
                }
            }
                  
            $distinctPages = array_unique($distinctPages);      
            
            
            $pagesWithQueries = array();
            $count = 0;
            if(!empty($distinctPages)){
                foreach ($distinctPages as $page) {
                    $pageUrlm = $page;
                   
                    $count++; 
                    if ($count > 5) {
                        $new_more_pages[] = $pageUrlm;
                        continue;
                    }
                    
                    $request = new Google_Service_Webmasters_SearchAnalyticsQueryRequest();
                    $request->setStartDate($startDate);
                    $request->setEndDate($endDate);
                    $request->setDimensions(['query']);
                    $request->setRowLimit(1000);
                    $filter = new \Google_Service_Webmasters_ApiDimensionFilter();
                    $filter->setDimension('page');
                    $filter->setExpression($pageUrlm);
                    $filterGroup = new \Google_Service_Webmasters_ApiDimensionFilterGroup();
                    $filterGroup->setFilters([$filter]);
                    $request->setDimensionFilterGroups([$filterGroup]);
                    
                    $query = $searchConsole->searchanalytics->query($siteUrl, $request);
                    $queries = $query->getRows();
                    usort($queries, function($a, $b) {
                        return $b['impressions'] - $a['impressions'];
                    });
        
                    $topQueries = array_slice($queries, 0, 5);
    
                    $pagesWithQueries[$count]['pageUrl'] = $pageUrlm;
                    $pagesWithQueries[$count]['queries'] = $topQueries;
                }
            }
            
            wp_send_json(['pages' => $pagesWithQueries, 'more_pages' => $new_more_pages]);
            die;
        }
        wp_send_json(['error' => true]);
        die;
    }
}
