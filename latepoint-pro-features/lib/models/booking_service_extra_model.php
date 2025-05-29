<?php

class OsBookingServiceExtraModel extends OsModel{
  var $id,
      $booking_id,
      $service_extra_id,
      $duration,
      $quantity = 1,
      $price,
      $created_at,
      $updated_at;

  function __construct($id = false){
    parent::__construct();
    $this->table_name = LATEPOINT_TABLE_BOOKINGS_SERVICE_EXTRAS;
    $this->nice_names = [];

    if($id){
      $this->load_by_id($id);
    }
  }

  protected function allowed_params($role = 'admin'){
    $allowed_params = array(  
                              'booking_id',
                              'service_extra_id',
                              'duration',
                              'quantity',
                              'price');
    return $allowed_params;
  }
  
  protected function params_to_save($role = 'admin'){
    $params_to_save = array('id',
                              'booking_id',
                              'service_extra_id',
                              'duration',
                              'quantity',
                              'price',
                              'created_at',
                              'updated_at');
    return $params_to_save;
  }
}
