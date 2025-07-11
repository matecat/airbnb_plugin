<?php

namespace Features\Airbnb\Model\SegmentDelivery;

use Model\DataAccess\AbstractDao;
use Model\Database;

class SegmentDeliveryDao extends AbstractDao {

    /**
     * @param $id_job
     *
     * @return bool
     */
    public static function isAJobDeliverable( $id_job ) {
        $conn = Database::obtain()->getConnection();
        $sql  = "SELECT id FROM segment_translations JOIN
            segment_notes on segment_notes.id_segment = segment_translations.id_segment 
            WHERE id_job = :id_job
            AND segment_notes.note LIKE 'source_hash%'
            limit 1;";

        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_job' => $id_job
        ] );

        $results = $stmt->fetchAll();

        return count( $results ) > 0;
    }
}