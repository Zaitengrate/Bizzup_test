<?php
declare(strict_types=1);
const BITRIX_URL = 'yoururl';

/**
 * @param string $method
 * @param array $params
 *
 * @return array
 */
function callBitrix(string $method, array $params = []): array {
    $url = BITRIX_URL . $method . '.json';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response ? json_decode($response, true) : [];
}

/**
 * @return array
 */
function getContacts(): array {
    $contacts = [];
    $start = 0;
    do {
        $response = callBitrix('crm.contact.list', [
            'start' => $start,
            'select' => ['ID', 'NAME', 'LAST_NAME', 'SECOND_NAME']
        ]);
        if (isset($response['result'])) {
            $contacts = array_merge($contacts, $response['result']);
            $start += count($response['result']);
        } else {
            break;
        }
    } while ($response['total'] > $start);

    return $contacts;
}

/**
 * @param array $contacts
 *
 * @return array
 */
function performNormalization(array $contacts): array
{
    $contacts_to_update = [];
    foreach ($contacts as $contact) {
        if (strpos($contact['NAME'], ' ')) {
            $name = explode(' ', $contact['NAME']);
            $contacts_to_update[] = [
                'ID' => $contact['ID'],
                'NAME' => $name[0],
                'LAST_NAME' => $contact['LAST_NAME'],
                'SECOND_NAME' => $name[1]
            ];
        }
    }

    return $contacts_to_update;
}

/**
 * @param array $contacts
 *
 * @return void
 */
function updateContacts(array $contacts): void
{
    foreach ($contacts as $new_contact) {
        callBitrix('crm.contact.update', [
            'id' => $new_contact['ID'],
            'fields' => [
                'NAME' => $new_contact['NAME'],
                'SECOND_NAME' => $new_contact['SECOND_NAME'],
                'LAST_NAME' => $new_contact['LAST_NAME']
            ]
        ]);
    }
}

$message = '';
$contacts = getContacts();

if (!$contacts) {
    $message = 'Unable to retrieve any contacts.';
} else {
    $contacts_to_update = performNormalization($contacts);
    if (!$contacts_to_update) {
        $message = 'No updates to the contacts are needed.';
    } else {
        updateContacts($contacts_to_update);
        $message = 'Contacts were updated.';
    }
}

echo $message;
