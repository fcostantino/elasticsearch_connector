<?php

/**
 * @file
 * Contains Drupal\elasticsearch_connector\Form.
 */

namespace Drupal\elasticsearch_connector\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\elasticsearch_connector\Entity\Cluster;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for the Cluster entity.
 */
class ClusterForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    if ($form_state->isRebuilding()) {
      $this->entity = $this->buildEntity($form, $form_state);
    }
    $form = parent::form($form, $form_state);
    // Get the entity and attach to the form state.
    $cluster = $this->getEntity();

    if ($cluster->isNew()) {
      $form['#title'] = $this->t('Add Elasticsearch Cluster');
    }
    else {
      $form['#title'] = $this->t('Edit Elasticsearch Cluster @label', array('@label' => $cluster->label()));
    }

    $this->buildEntityForm($form, $form_state, $cluster);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntityForm(array &$form, FormStateInterface $form_state, Cluster $cluster) {
    $form['cluster'] = array(
      '#type'  => 'value',
      '#value' => $cluster,
    );

    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => t('Administrative cluster name'),
      '#default_value' => empty($cluster->name) ? '' : $cluster->name,
      '#description' => t('Enter the administrative cluster name that will be your Elasticsearch cluster unique identifier.'),
      '#required' => TRUE,
    );

    $form['cluster_id'] = array(
      '#type' => 'machine_name',
      '#title' => t('Cluster id'),
      '#default_value' => !empty($cluster->cluster_id) ? $cluster->cluster_id : '',
      '#maxlength' => 125,
      '#description' => t('A unique machine-readable name for this Elasticsearch cluster.'),
      '#machine_name' => array(
        'exists' => ['Drupal\elasticsearch_connector\Entity\Cluster', 'load'],
        'source' => array('name'),
      ),
      '#required' => TRUE,
      '#disabled' => !empty($cluster->cluster_id),
    );

    $form['url'] = array(
      '#type' => 'textfield',
      '#title' => t('Server URL'),
      '#default_value' => !empty($cluster->url) ? $cluster->url : '',
      '#description' => t(
        'URL and port of a server (node) in the cluster. ' .
        'Please, always enter the port even if it is default one. ' .
        'Nodes will be automatically discovered. ' .
        'Examples: http://localhost:9200 or https://localhost:443.'),
      '#required' => TRUE,
    );

    $form['status_info'] = $this->clusterFormInfo($cluster);

    $default = Cluster::getDefaultCluster();
    $form['default'] = array(
      '#type' => 'checkbox',
      '#title' => t('Make this cluster default connection'),
      '#description' => t('If the cluster connection is not specified the API will use the default connection.'),
      '#default_value' => (empty($default) || (!empty($cluster->cluster_id) && $cluster->cluster_id == $default)) ? '1' : '0',
    );

    $form['options'] = array(
      '#tree' => TRUE,
    );

    $form['options']['multiple_nodes_connection'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use multiple nodes connection'),
      '#description' => t('Automatically discover all nodes and use them in the cluster connection. ' .
        'Then the Elasticsearch client can distribute the query execution on random base between nodes.'),
      '#default_value' => (!empty($cluster->options['multiple_nodes_connection']) ? 1 : 0),
    );

    $form['status'] = array(
      '#type' => 'radios',
      '#title' => t('Status'),
      '#default_value' => isset($cluster->status) ? $cluster->status : Cluster::ELASTICSEARCH_CONNECTOR_STATUS_ACTIVE,
      '#options' => array(
        Cluster::ELASTICSEARCH_CONNECTOR_STATUS_ACTIVE    => t('Active'),
        Cluster::ELASTICSEARCH_CONNECTOR_STATUS_INACTIVE  => t('Inactive'),
      ),
      '#required' => TRUE,
    );

    $form['options']['use_authentication'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use authentication'),
      '#description' => t('Use HTTP authentication method to connect to Elasticsearch.'),
      '#default_value' => (!empty($cluster->options['use_authentication']) ? 1 : 0),
      '#suffix' => '<div id="hosting-iframe-container">&nbsp;</div>',
    );

    $form['options']['authentication_type'] = array(
      '#type' => 'radios',
      '#title' => t('Authentication type'),
      '#description' => t('Select the http authentication type.'),
      '#options'  => array(
        'Digest' => t('Digest'),
        'Basic'  => t('Basic'),
        'NTLM'   => t('NTLM')
      ),
      '#default_value' => (!empty($cluster->options['authentication_type']) ? $cluster->options['authentication_type'] : 'Digest'),
      '#states' => array (
        'visible' => array(
          ':input[name="options[use_authentication]"]' => array('checked' => TRUE),
        ),
      ),
    );

    $form['options']['username'] = array(
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#description' => t('The username for authentication.'),
      '#default_value' => (!empty($cluster->options['username']) ? $cluster->options['username'] : ''),
      '#states' => array (
        'visible' => array(
          ':input[name="options[use_authentication]"]' => array('checked' => TRUE),
        ),
      ),
    );

    $form['options']['password'] = array(
      '#type' => 'textfield',
      '#title' => t('Password'),
      '#description' => t('The password for authentication.'),
      '#default_value' => (!empty($cluster->options['password']) ? $cluster->options['password'] : ''),
      '#states' => array (
        'visible' => array(
          ':input[name="options[use_authentication]"]' => array('checked' => TRUE),
        ),
      ),
    );

    $form['options']['timeout'] = array(
      '#type' => 'textfield',
      '#title' => t('Connection timeout'),
      '#size'  => 20,
      '#required' => TRUE,
      '#element_validate' => array('element_validate_number'),
      '#description' => t('After how many seconds the connection should timeout if there is no connection to Elasticsearch.'),
      '#default_value' => (!empty($cluster->options['timeout']) ? $cluster->options['timeout'] : Cluster::ELASTICSEARCH_CONNECTOR_DEFAULT_TIMEOUT),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $values = $form_state->getValues();

    // TODO: Check for valid URL when we are submitting the form.
    // Set default cluster.
    $default = Cluster::getDefaultCluster();
    if (empty($default) && !$values['default']) {
      $default = Cluster::setDefaultCluster($values['cluster_id']);
    }
    elseif ($values['default']) {
      $default = Cluster::setDefaultCluster($values['cluster_id']);
    }

    if ($values['default'] == 0 && !empty($default) && $default == $values['cluster_id']) {
      drupal_set_message(
        t(
          'There must be a default connection. %name is still the default
          connection. Please change the default setting on the cluster you wish
          to set as default.',
          array(
            '%name' => $values['name'],
          )
        ),
        'warning'
      );
    }
  }

  /**
   * Build the cluster info table for the edit page.
   */
  protected function clusterFormInfo(Cluster $cluster = NULL) {
    $element = array();

    if (isset($cluster->url)) {
      try {
        $cluster_info = $cluster->getClusterInfo();
        if ($cluster_info) {
          $headers = array(
            array('data' => t('Cluster name')),
            array('data' => t('Status')),
            array('data' => t('Number of nodes')),
          );

          if (isset($cluster_info['state'])) {
            $rows = array(
              array(
                $cluster_info['health']['cluster_name'],
                $cluster_info['health']['status'],
                $cluster_info['health']['number_of_nodes'],
              )
            );

            $element = array(
              '#theme' => 'table',
              '#header' => $headers,
              '#rows' => $rows,
              '#attributes' => array(
                'class' => array('admin-elasticsearch'),
                'id' => 'cluster-info'
              ),
            );
          }
          else {
            $rows = array(
              array(
                t('Unknown'),
                t('Unavailable'),
                '',
              )
            );

            $element = array(
              '#theme' => 'table',
              '#header' => $headers,
              '#rows' => $rows,
              '#attributes' => array(
                'class' => array('admin-elasticsearch'),
                'id' => 'cluster-info'
              ),
            );
          }
        }
        else {
          $element['#type'] = 'markup';
          $element['#markup'] = '<div id="cluster-info">&nbsp;</div>';
        }
      }
      catch (\Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Only save the server if the form doesn't need to be rebuilt.
    if (!$form_state->isRebuilding()) {
      try {
        $cluster = $this->getEntity();
        $cluster->save();
        drupal_set_message(t('Cluster %label has been updated.', array('%label' => $cluster->label())));
        $form_state->setRedirect('entity.elasticsearch_cluster.canonical', array('elasticsearch_cluster' => $cluster->id()));
      }
      // TODO: This should not be SearchApiException.
      catch (SearchApiException $e) {
        $form_state->setRebuild();
        watchdog_exception('elasticsearch_connector', $e);
        drupal_set_message($this->t('The cluster could not be saved.'), 'error');
      }
    }
  }

}
