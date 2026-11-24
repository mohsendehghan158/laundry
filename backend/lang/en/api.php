<?php

declare(strict_types=1);

return [
    'errors' => [
        'validation_failed' => 'The given data was invalid.',
        'unauthenticated' => 'You need to sign in to continue.',
        'forbidden' => 'You are not allowed to do this.',
        'not_found' => 'The requested item was not found.',
        'method_not_allowed' => 'This operation is not allowed on this address.',
        'conflict' => 'This operation conflicts with the current state.',
        'csrf_mismatch' => 'Your session has expired. Refresh the page and try again.',
        'too_many_requests' => 'Too many requests. Please try again shortly.',
        'bad_request' => 'The request is invalid.',
        'service_unavailable' => 'The service is temporarily unavailable.',
        'http_error' => 'The request could not be completed.',
        'server_error' => 'Something went wrong. Please try again.',

        'branch_last' => 'The last branch cannot be deleted.',
        'user_cannot_delete_self' => 'You cannot delete your own account.',
        'user_cannot_deactivate_self' => 'You cannot deactivate your own account.',
        'user_last_owner' => 'At least one active owner must remain.',
        'role_owner_immutable' => 'The permissions of the owner role cannot be changed.',
        'role_permission_beyond_own' => 'You cannot grant a permission you do not hold yourself.',
    ],
];
