<?php
/**
 * Media Manager
 *
 * @package       PaperTiger:MediaManager
 * @author        Paper Tiger
 * @copyright     Copyright (c) 2020 Paper Tiger
 * @link          https://www.papertiger.com/
 */

namespace papertiger\mediamanager\services;

use Craft;
use craft\base\Component;

use papertiger\mediamanager\MediaManager;
use papertiger\mediamanager\records\Show as ShowRecord;

class Show extends Component
{
    // Public Methods
    // =========================================================================

    public function getShow( $id = false, $first = false )
    {
        $show = new ShowRecord();

        if( $id ) {

            $activeShow = $show->findOne( $id );

            if( $activeShow ) {
                $this->updateLastActive( $activeShow->id );
            }

            $returnedActiveShow = [
                'id'     => $activeShow[ 'id' ],
                'name'   => $activeShow[ 'name' ],
                'apiKey' => $activeShow[ 'apiKey' ],
                'siteId' => $activeShow[ 'siteId' ],
            ];

            if( $returnedActiveShow[ 'siteId' ] ) {
                $returnedActiveShow[ 'siteId' ] = json_decode( $returnedActiveShow[ 'siteId' ] );
            }

            return $returnedActiveShow;
        }

        if( !$id && $first ) {

            $activeShow = $show->find()
                                ->where( [ '=', 'lastActive', true ] )
                                ->orderBy( 'id' )
                                ->one();

            if( !$activeShow ) {

                $activeShow = $show->find()
                                ->where( [ '!=', 'name', '' ] )
                                ->orderBy( 'id' )
                                ->one();
            
                if( $activeShow ) {
                    $this->updateLastActive( $activeShow->id );
                }
            }

            if( $activeShow ) {

                $returnedActiveShow = [
                    'id'     => $activeShow[ 'id' ],
                    'name'   => $activeShow[ 'name' ],
                    'apiKey' => $activeShow[ 'apiKey' ],
                    'siteId' => $activeShow[ 'siteId' ],
                ];

                if( $returnedActiveShow[ 'siteId' ] ) {
                    $returnedActiveShow[ 'siteId' ] = json_decode( $returnedActiveShow['siteId'] );
                }

                return $returnedActiveShow;
            }
        }

        return $show->find()
                ->where( [ '!=', 'name', '' ] )
                ->orderBy( [ '(id)' => SORT_ASC ] )
                ->all();
    }

    public function saveShow( string $name, int $id, string $apiKey, string $siteId )
    {
        $show         = new ShowRecord();
        $existingShow = $show->find()
                                ->where( [ '=', 'id', $id ] )
                                ->orWhere( [ '=', 'name', $name ] )
                                ->one();

        if( $existingShow ) {

            if( $name ) {
                $existingShow->name = $name;
            }

            if( $apiKey ) {
                $existingShow->apiKey = $apiKey;
            }

            if( $siteId ) {
                $existingShow->siteId = $siteId;
            }
            
            $existingShow->update();

            return $existingShow;
        }

        $show->name   = $name;
        $show->apiKey = $apiKey;
        $show->siteId = $siteId;
        $show->save();

        return $show;
    }

    public function deleteShow( int $id )
    {
        $show         = new ShowRecord();
        $existingShow = $show->find()
                                ->where( [ '=', 'id', $id ] )
                                ->one();

        if( $existingShow ) {

            $existingShow->delete();
            return $existingShow;

        } else {

            $show->addErrors( [ 'error' => 'Show with ID# '. $id .' not found' ] );
            return $show;
        }
    }


    // Private Methods
    // =========================================================================

    private function updateLastActive( int $id )
    {
        $show = new ShowRecord();

        $show::updateAll( [ 'lastActive' => false ] );

        $activeShow = $show->find()
                                ->where( [ '=', 'id', $id ] )
                                ->one();

        $activeShow->lastActive = true;
        $activeShow->update();
    }
}
