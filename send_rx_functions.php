<?php
/**
 * @file
 * Helper Send RX functions.
 */

/**
 * Gets Send RX config from project.
 *
 * @param int $project_id
 *   The project ID.
 * @param string $project_type
 *   The RX Send project type, which can be "patient" or "pharmacy".
 *
 * @return object
 *   A keyed JSON containing the Send RX project configuration.
 *   If patient project, the object should include the following keys:
 *     - targetProjectId: Target pharmacy project ID.
 *     - senderClass: The sender class name, that extends RXSender.
 *     - sendDefault: Flag that defines whether the prescription should be sent
 *       by default.
 *     - lockInstruments: The instruments to be locked after the message is sent.
 *   If pharmacy project:
 *     - pdfTemplate: The PDF markup (HTML & CSS) of the prescription.
 *     - messageSubject: The message subject.
 *     - message_body: The message body.
 *   Returns FALSE if the project is not configure properly.
 */
function send_rx_get_project_config($project_id, $project_type) {
    // TODO.
    // Fetches JSON from database and validates the required fields according to
    // the project type. Example: check if sender class exists.
}

/**
 * Creates the sender object for the given Send RX project.
 *
 * @param int $project_id
 *   Data entry project ID.
 * @param int $event_id
 *   Data entry event ID.
 * @param int $patient_id
 *   Data entry record ID.
 * @param string $username
 *   The username. Defaults to the current one.
 *
 * @return object|bool
 *   An object instance of RXSender extension for the given project, if success.
 *   False otherwise.
 */
function send_rx_get_sender($project_id, $event_id, $patient_id, $username = USERID) {
    if (!$config = send_rx_get_project_config($project_id, 'patient')) {
        return false;
    }

    $class = $config->senderClass;
    return new $class($project_id, $event_id, $patient_id, $username);
}

/**
 * Applies Piping on the given subject string.
 *
 * Example: "Hello, [first_name]!" turns into "Hello, Joe Doe!".
 *
 * @param string $subject
 *   The string be processed. 
 * @param array $data
 *   An array of source data. It supports nesting values, which are mapped on the
 *   subject string as nesting square brackets (e.g. [user][first_name]).
 *
 * @return string
 *   The processed string, with the replaced values from source data.
 */
function send_rx_piping($subject, $data) {
    // Checking for wildcards.
    if (!$brackets = getBracketedFields($subject, true, true, false)) {
        return $subject;
    }

    foreach (array_keys($brackets) as $wildcard) {
        $parts = explode('.', $wildcard);
        $count = count($parts);

        if ($count == 1) {
            // This wildcard has no children.
            if (!isset($data[$wildcard])) {
                continue;
            }

            $value = $data[$wildcard];
        }     
        else {
            $child = array_shift($parts);
            if (!isset($data[$child]) || !is_array($data[$child])) {
                continue;
            }
 
            // Wildcard with children. Call function recursively.
            $value = send_rx_piping('[' . implode('][', $parts) . ']', $data[$child]);
        }

        // Search and replace.
        $subject = str_replace('[' . str_replace('.', '][', $wildcard) . ']', $value, $subject);
    }
    
    return $subject;
}

/**
 * Generates a PDF file.
 *
 * @param string $contents
 *   Markup (HTML + CSS) of PDF contents.
 * @param string $file_path
 *   The file path to save the file.
 *
 * @return bool
 *   TRUE if success, FALSE otherwise.
 */
function send_rx_generate_pdf_file($contents, $file_path) {
    // TODO.
}

/**
 * Gets file contents from the given edocs file.
 *
 * @param int $file_id
 *   The edocs file id.
 *
 * @return string
 *   The file contents.
 */
function send_rx_get_file_contents($file_id) {
    $sql = 'SELECT * FROM redcap_edocs_metadata WHERE doc_id = ' . db_escape($file_id);
    $q = db_query($sql);

    if (!db_num_rows($q)) {
        return false;
    }

    $file = db_fetch_assoc($q);
    $file_path = EDOC_PATH . $file['stored_name'];

    if (!file_exists($file_path) || !is_file($file_path)) {
        return false;
    }

    return file_get_contents($file_path);
}

/**
 * Uploads an existing field to the edocs table.
 *
 * @param string $file_path
 *   The location of the file to be uploaded.
 *
 * @return int
 *   The edocs file ID if success, 0 otherwise.
 */
function send_rx_upload_file($file_path) {
    if (!file_exists($file_path) || !is_file($file_path)) {
        return false;
    }

    $file = array(
        'name'=> basename($file_path),
        'type'=> mime_content_type($file_path),
        'size'=> filesize($file_path),
        'tmp_name'=> $file_path,
    );

    return Files::uploadFile($file);
}

/**
 * Creates or updates a data entry field value.
 *
 * @param int $project_id
 *   Data entry project ID.
 * @param int $event_id
 *   Data entry event ID.
 * @param int $record_id
 *   Data entry record ID.
 * @param string $field_name
 *   Machine name of the field to be updated.
 * @param mixed $value
 *   The value to be saved.
 * @param int $instance
 *   (optional) Data entry instance ID (for repeat instrument cases).
 *
 * @return bool
 *   TRUE if success, FALSE otherwise.
 */
function send_rx_save_record_field($project_id, $event_id, $record_id, $field_name, $value, $instance = null) {
    // TODO.
}

/**
 * Gets data entry record information.
 *
 * If more than one event is fetched, it returns the first one.
 *
 * @param int $project_id
 *   Data entry project ID.
 * @param int $record_id
 *   Data entry record ID.
 * @param int $event_id
 *   (optional) Data entry event ID.
 *
 * @return array|bool
 *   Data entry record information array. FALSE if failure.
 */
function send_rx_get_record_data($project_id, $record_id, $event_id = null) {
    $data = REDCap::getData($project_id, 'array', $record_id, null, $event_id);
    if (empty($data[$record_id])) {
        return false;
    }

    if ($event_id) {
        return $data[$record_id][$event_id];
    }

    return reset($data[$record_id]);
}

/**
 * Gets repeat instrument instances information from a given data entry record.
 *
 * If more than one event is fetched, it returns the first one.
 *
 * @param int $project_id
 *   Data entry project ID.
 * @param int $record_id
 *   Data entry record ID.
 * @param int $event_id
 *   (optional) Data entry event ID.
 *
 * @return array|bool
 *   Array containing repeat instrument instances information. FALSE if failure.
 */
function send_rx_get_repeat_instrument_instances($project_id, $record_id, $instrument_name, $event_id = null) {
    $data = REDCap::getData($project_id, 'array', $record_id, null, $event_id);
    if (empty($data[$record_id]['repeat_instances'])) {
        return false;
    }

    $data = $event_id ? $data[$record_id]['repeat_instances'][$event_id] : reset($data['repeat_instances'][$record_id]);
    if (empty($data[$instrument_name])) {
        return false;
    }

    return $data[$instrument_name];
}

/**
 * Locks for updates the given instruments of the given data entry record.
 *
 * @param int $project_id
 *   Data entry project ID.
 * @param int $record_id
 *   Data entry record ID.
 * @param int $instruments
 *   (optional) Array of instruments names. Leave blank to block all instruments.
 * @param int $event_id
 *   (optional) Data entry event ID. Leave blank to block all events.
 *
 * @return bool
 *   TRUE if success, FALSE otherwise.
 */
function send_rx_lock_instruments($project_id, $record_id, $instruments = null, $event_id = null) {
    // TODO.
}

/**
 * Gets the pharmacies that the user belongs to.
 *
 * @param int $project_id
 *   The patient or pharmacy project ID.
 * @param string $username
 *   The username. Defaults to the current one.
 * @param string $project_type
 *   Specifies the incoming project type: "patient" or "pharmacy".
 *   Defaults to "patient".
 *
 * @return array|bool
 *   Array of pharmacies names, keyed by pharmacy ID. False if error.
 */
function send_rx_get_user_pharmacies($project_id, $username = USERID, $project_type = 'patient') {
    if ($project_type == 'patient') {
        if (!$config = send_rx_get_project_config($project_id, $project_type)) {
            return false;
        }

        // Gets pharmacy project from the patient project.
        $project_id = $config->targetProjectId;
        $project_type = 'pharmacy';
    }

    // Checking if pharmacy project is ok.
    if (send_rx_get_project_config($project_id, $project_type)) {
        return false;
    }

    $pharmacies = array();

    $data = REDCap::getData($project_id, 'array', null, 'send_rx_username');
    foreach ($data as $pharmacy_id => $pharmacy_info) {
        if (empty($pharmacy_info['repeat_instances'])) {
            continue;
        }

        if (empty($pharmacy_info['repeat_instances']['rx_send_users'])) {
            continue;
        }

        foreach ($pharmacy_info['repeat_instances']['rx_send_users'] as $user_info) {
            if ($username == $user_info['send_rx_username']) {
                // The user belongs to this pharmacy.
                $pharmacies[$pharmacy_id] = $pharmacy_info['send_rx_pharmacy_name'];
                break;
            }
        }
    }

    return $pharmacies;
}
