<?php

define('BASE_API_URL', 'https://ap-southeast-2.api.vaultre.com.au/api/v1.3/properties/');

class Vaultre
{
    private $token = 'SECRET_TOKEN_HERE';
    private $key = 'SECRET_KEY_HERE';

    /*  @working ( Fetch all properties )
    	@returns array of all properties || false
    */

    public function getAllSaleProperties()
    {
        $response = $this->curlSetup('sale?pagesize=200');
        $data = json_decode($response, true);
        if (empty($data)) return false;
        $items = $data['items'] ?? [];
        return $items;
    }

    /*  @working ( Update properties modified a day ago, store JSON in options column )
    	@returns Array of modified properties
    */
    public function updatePropertiesList()
    {
        $date = date('Y-m-d H:i:s', strtotime("-1 days"));
        $response = $this->curlSetup('sale?modifiedSince=' . urlencode($date) . '&pagesize=200');
        $data = json_decode($response, true);

        if (empty($data) || !isset($data['items'])) return false;
        $items = $data['items'];
        update_field('update_properties', json_encode($items, JSON_PRETTY_PRINT) , 'option');
        return $items;
    }

    
    /*  @working Create/update modified properties
    	@params  Array of modified properties
    	@returns Array of Updated Post IDs
    */
    public function updateModifiedProperties()
    {
    	$items    = get_field('update_properties', 'option');
        $result   = [];
    	if( !empty($items) ) {
        	$items = json_decode($items, true);
        	if(count($items) > 0) {
            $slicedPropertiesToUpdate = array_slice($items, 0, 30);
	        $dayAgoTime   = date('Ymd', strtotime('- 1 days'));
	        
	        foreach ($slicedPropertiesToUpdate as $key => $data)
	        {
	            $inserted = !empty( $data['inserted'] ) ? date( 'Ymd', strtotime( $data['inserted'] ) ) : 0;
	            $action   = $inserted == $dayAgoTime ? 0 : 1;
	            $result[] = $this->saveProperty($data, $action);
	        }

            $pendingResult = array_slice($items, 30);
            update_field('update_properties', json_encode($pendingResult, JSON_PRETTY_PRINT) , 'option'); 
        }
        unset( $items );
        return $result;
    }


    /*  @params (array) $data, (int) $action i.e 0 == create, 1 == update
    	@return  $new_post
    */
    public function saveProperty($dataArray, $action = 0)
    {
        $meta = $this->prepareMetaData( $dataArray );
        $data = array(
            'post_type' => 'property',
            'post_status' => !empty($meta['publishedToWeb']) ? 'publish' : 'draft',
            'post_title' => !empty($meta['heading']) ? $meta['heading'] : $meta['displayAddress'],
            'post_content' => null,
            'meta_input' => $meta
        );
        try
        {
            if ($action > 0)
            {
                $id = 0;
                $run = new WP_Query([
                    'post_type' => 'property',
                    'meta_key' => 'prop_id',
                    'meta_value' => $dataArray['id'],
                    'posts_per_page' => 1
            	]);

                if ($run->have_posts())
                {
                    while ($run->have_posts())
                    {
                        $run->the_post();
                        $id = get_the_ID();
                    }
                }
            }

            $post = $action == 0 ? wp_insert_post($data) : wp_update_post(array_merge(['ID' => $id], $data));

            wp_set_object_terms($post, [$meta['suburb']], 'suburb');

            wp_set_object_terms($post, [ucfirst($meta['status']) ], 'status');

            wp_set_object_terms($post, [!empty($meta['publishedToWeb']) ? 'yes' : 'no'], 'publishtoweb');

            if (!$post)
            {
                throw new Exception("Post insertion/updation failed");
            }
            return $post;
        }
        catch(Exception $e)
        {
            // error_log(date('Y/m/d g:i:s a') . ' => ' . $e->getMessage());
            return false;
        }
    }

    /*	@params array $data
    	@return  array $meta
    */
    public function prepareMetaData( $data )
    {

        $meta = array(
            'id' 		=> $data['id'],
            'displayAddress' 	=> $data['displayAddress'],
            'type' 		=> $data['propertyClass']['name'],
            'priceQualifier' 	=> $data['priceQualifier'],
            'landValue' 	=> $data['landValue'],
            'agentPriceOpinion' => $data['agentPriceOpinion'],
            'volumeNumber' 	=> $data['volumeNumber'],
            'saleLifeId' 	=> $data['saleLifeId'],
            'suburb' 		=> $data['address']['suburb']['name'],
            'postcode' 		=> $data['address']['suburb']['postcode'],
            'state' 		=> $data['address']['suburb']['state']['abbreviation'],
            'bed' 		=> $data['bed'],
            'bath' 		=> $data['bath'],
            'carports' 		=> $data['carports'],
            'garages' 		=> $data['garages'],
            'status' 		=> $data['status'],
            'description' 	=> $desc,
            'features' 		=> $features,
            'contactStaff' 	=> $data['contactStaff'],
            'yearBuilt' 	=> $data['yearBuilt'],
            'methodOfSale' 	=> $data['methodOfSale']['name'],
            'corelogicId' 	=> $data['corelogicId'],
            'displayPrice' 	=> $data['displayPrice'],
            'price' 		=> (int)filter_var($data['displayPrice'], FILTER_SANITIZE_NUMBER_INT),
            'searchPrice' 	=> $data['searchPrice'],
            'sellingFeeFixed' 	=> $data['sellingFeeFixed'],
            'auctionDetails' 	=> $data['auctionDetails'],
            'photos' 		=> $photos['photos'],
            'floorplan' 	=> $photos['floorplan'],
            'geolocation' 	=> $data['geolocation'],
            'landArea' 		=> $data['landArea']['value'],
            'landAreaUnits' 	=> $data['landArea']['units'],
            'inserted' 		=> $data['inserted'],
            'modified' 		=> $data['modified'],
            'energyRating' 	=> $data['energyRating'],
            'portalStatus' 	=> $data['portalStatus'],
            'publishedToWeb' 	=> $data['publishedToWeb'],
            'response' 		=> json_encode($data)
        );
        return $meta;
    }


    /*	@working Hit API endpoint and return the response
    	@params API endpoint
    	@return  array $response
    */
    private function curlSetup( $endpoint )
    {
        $curl = curl_init();
        curl_setopt_array(
        	$curl, 
        	[
        		CURLOPT_URL => BASE_API_URL.$endpoint,
        		CURLOPT_RETURNTRANSFER => true,
        		CURLOPT_TIMEOUT => 0, 
        		CURLOPT_FOLLOWLOCATION => true, 
        		CURLOPT_CUSTOMREQUEST => 'GET', 
        		CURLOPT_HTTPHEADER => array(
            		'X-Api-Key: ' . $this->key,
            		'Accept: application/json',
            		'Authorization: Bearer ' . $this->token
        		) 
        	]
        );

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}
