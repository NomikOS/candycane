<?php
/*
  Customizable Behavior
*/
class CustomizableBehavior extends ModelBehavior {
  // array (example)
  // array[{n}]['CustomValue'][{fieldname}]
  var $custom_field_values = null;
  
  function setup(&$Model, $config = array()) {
    $settings = $config;
    $this->settings[$Model->alias] = $settings;
    return true;
  }

  /**
   * Check between Model->data[modelname][custom_field_values][] and Database values
   */
  function beforeValidate(&$Model){
    return true;
  }
  /**
   * Create save data
   * Create data from Model->data[modelname][custom_field_values] 
   */
  function beforeSave(&$Model) {
    $this->custom_field_values = $this->_create_save_data($Model);
    return true;
  }
  /**
   * Save custom field values after Main Model
   */
  function afterSave(&$Model, $created) {
    return $this->_save_custom_field_values($Model, $created);
  }

  /**
   * Add relation of CustomValues
   */
  function afterFind(&$Model, $results, $primary = false) {
    $single = false;
    if(empty($results[0])) {
      $results = array($results);
      $single = true;
    }
    if(is_array($results)) {
      foreach($results as $index => $result) {
        if(!empty($result[$Model->name]) && !empty($result[$Model->name]['id'])) {
          $customValueModel = & ClassRegistry::init('CustomValue');
          $conditions = array('customized_type'=> $Model->name, 'customized_id'=>$result[$Model->name]['id']);
          $order = 'CustomField.position';
          $values = $customValueModel->find('all', compact('conditions', 'order'));
          if(!empty($values)) {
            $results[$index]['CustomValue'] = array();
            foreach($values as $value) {
              $value['CustomValue']['CustomField'] = $value['CustomField'];
              $results[$index]['CustomValue'][] = $value['CustomValue'];
            }
          }
        }
      }
    }
    if($single) {
      $results = $results[0];
    }
    return $results;
  }
  
  /**
   * Get available field values 
   */
  function available_custom_fields(&$Model, $project_id=false, $tracker_id=false) {
    $customValueModel = & ClassRegistry::init('CustomValue');
    $is_for_all = true;
    if(isset($this->settings[$Model->alias]['is_for_all'])) { 
      $is_for_all = $this->settings[$Model->alias]['is_for_all'];
    }
    $for_alls = $customValueModel->CustomField->find('all', 
        array('conditions' => array('type'=> $Model->name.'CustomField', 'is_for_all'=>$is_for_all), 'order'=>'position'));
    if(!empty($project_id)) {
      $CustomFieldsProject = & ClassRegistry::init('CustomFieldsProject');
      $for_projects = $CustomFieldsProject->find('all', 
        array('conditions' => array('CustomField.type'=> $Model->name.'CustomField', 'project_id'=>$project_id), 'order'=>'CustomField.position'));
    } else {
      $for_projects = array();
    }
    $availables = array();
    $result = array();
    foreach($for_alls as $for_all) {
      $availables[$for_all['CustomField']['position']] = $for_all;
    }
    foreach($for_projects as $for_project) {
      $availables[$for_project['CustomField']['position']] = $for_project;
    }
    if(!empty($tracker_id) && !empty($availables)) {
      $ids = array();
      foreach($availables as $available) {
        $ids[] = $available['CustomField']['id'];
      }
      $CustomFieldsTracker = & ClassRegistry::init('CustomFieldsTracker');
      $for_tracker_ids = $CustomFieldsTracker->find('all', array(
        'conditions' => array('CustomField.type'=> $Model->name.'CustomField', 'tracker_id'=>$tracker_id, 'CustomField.id'=>$ids), 
        'fields'=>array('CustomField.position'),
        'order'=>"CustomField.position"
      ));
      if(!empty($for_tracker_ids)) {
        $result = array();
        foreach($for_tracker_ids as $for_tracker_id)
          $result[] = $availables[$for_tracker_id['CustomField']['position']];
      }
    }
    if(empty($result)) {
      $result = $availables;
    }
    return $result;
  }

   // ==== privates 

  function _create_save_data(&$Model) {
    $data = array();
    if(!empty($Model->data[$Model->name]['custom_field_values'])) {
      // Create save data from POST data.
      foreach($Model->data[$Model->name]['custom_field_values'] as $key => $input) {
        $data[] = array('CustomValue' => array(
          'customized_type' => $Model->name,
          'customized_id' => $Model->id,
          'custom_field_id' => $key,
          'value' => $input
        ));
      }
    }
    return $data;
  }

  function _save_custom_field_values(&$Model, $created) {
    if($created) {
      $insertId = $Model->getLastInsertID();
    }
    $customValueModel = & ClassRegistry::init('CustomValue');
    if(!empty($this->custom_field_values)) {
      foreach($this->custom_field_values as $custom_value) {
        $customValueModel->create();
        if($created) {
          $custom_value['CustomValue']['customized_id'] = $insertId;
        }
        $customValueModel->save($custom_value);
      }
    }
    $this->custom_field_values = null;
  }
}

?>