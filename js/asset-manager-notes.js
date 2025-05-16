jQuery(document).ready(function($) {
    // Ensure the assetManagerNotes object and post ID are available
    if (typeof assetManagerNotes === 'undefined' || !assetManagerNotes.postId) {
        console.error('Asset Manager Notes data not available.');
        return;
    }

    var assetNoteMediaUploader;
    var $attachmentList = $('#asset-note-attachment-list');
    var $attachmentIdsInput = $('#new-asset-note-attachment-ids');
    var attachedFileIds = []; // Array to store attachment IDs

    // Function to update the hidden input field with attachment IDs
    function updateAttachmentIdsInput() {
        $attachmentIdsInput.val(attachedFileIds.join(','));
    }

    // Handle adding attachments
    $('#add-asset-note-attachment').on('click', function(e) {
        e.preventDefault();

        // If the uploader already exists, open it
        if (assetNoteMediaUploader) {
            assetNoteMediaUploader.open();
            return;
        }

        // Create the WordPress media uploader
        assetNoteMediaUploader = wp.media({
            title: assetManagerNotes.addAttachmentText,
            button: {
                text: assetManagerNotes.addAttachmentText // Use the same text for the button
            },
            multiple: true // Allow multiple file selection
        });

        // When files are selected
        assetNoteMediaUploader.on('select', function() {
            var attachments = assetNoteMediaUploader.state().get('selection').toJSON();

            attachments.forEach(function(attachment) {
                // Add attachment ID to the array if not already present
                if (attachedFileIds.indexOf(attachment.id) === -1) {
                    attachedFileIds.push(attachment.id);

                    // Add the attachment name and a remove button to the list
                    var listItem = $('<li>').attr('data-attachment-id', attachment.id);
                    var fileLink = $('<a>').attr('href', attachment.url).attr('target', '_blank').text(attachment.title);
                    var removeButton = $('<button type="button" class="button button-small delete-attachment">').text(assetManagerNotes.removeAttachmentText);

                    listItem.append(fileLink).append(' ').append(removeButton); // Add a space between link and button
                    $attachmentList.append(listItem);
                }
            });

            // Update the hidden input field
            updateAttachmentIdsInput();
        });

        // Open the uploader
        assetNoteMediaUploader.open();
    });

    // Handle removing attachments
    $attachmentList.on('click', '.delete-attachment', function() {
        var $listItem = $(this).closest('li');
        var attachmentIdToRemove = $listItem.data('attachment-id');

        // Remove the ID from the array
        attachedFileIds = attachedFileIds.filter(id => id !== attachmentIdToRemove);

        // Remove the list item from the UI
        $listItem.remove();

        // Update the hidden input field
        updateAttachmentIdsInput();
    });


    // Handle saving the note via AJAX
    $('#save-asset-note').on('click', function(e) {
        e.preventDefault();

        var $saveButton = $(this);
        var $noteTextarea = $('#new-asset-note');
        var $spinner = $('#asset-note-spinner');
        var $messageDiv = $('#asset-note-message');
        var noteContent = $noteTextarea.val().trim();
        var attachmentIds = $attachmentIdsInput.val(); // Get comma-separated IDs

        // Don't save if note and attachments are empty
        if (noteContent === '' && attachedFileIds.length === 0) {
            $messageDiv.removeClass('notice notice-success notice-error').addClass('notice notice-warning').html('<p>' + assetManagerNotes.noteEmptyMessage + '</p>').show();
            return;
        }

        // Show spinner and disable button
        $saveButton.prop('disabled', true);
        $spinner.css('visibility', 'visible');
        $messageDiv.hide().empty(); // Hide previous messages

        // AJAX request
        $.ajax({
            url: assetManagerNotes.ajaxurl,
            type: 'POST',
            data: {
                action: 'am_save_asset_note', // The AJAX action to call
                nonce: assetManagerNotes.nonce, // The nonce for verification
                post_id: assetManagerNotes.postId, // The current post ID
                note_content: noteContent,
                attachment_ids: attachmentIds // Pass comma-separated IDs
            },
            success: function(response) {
                if (response.success) {
                    // Add the new note HTML to the top of the notes list
                    $('.asset-notes-list ul').prepend(response.data.note_html);
                     // If the list was empty, remove the "No notes added yet" paragraph
                    $('.asset-notes-list p:contains("No notes added yet.")').remove();

                    // Clear the textarea and attachment list
                    $noteTextarea.val('');
                    $attachmentList.empty();
                    attachedFileIds = []; // Reset the array
                    updateAttachmentIdsInput(); // Clear the hidden input

                    // Show success message
                    $messageDiv.removeClass('notice notice-error notice-warning').addClass('notice notice-success').html('<p>' + response.data.message + '</p>').show();
                } else {
                    // Show error message
                    $messageDiv.removeClass('notice notice-success notice-warning').addClass('notice notice-error').html('<p>' + response.data.message + '</p>').show();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                // Show a generic error message
                $messageDiv.removeClass('notice notice-success notice-warning').addClass('notice notice-error').html('<p>' + '<?php esc_html_e("An error occurred while saving the note.", "asset-manager"); ?>' + '</p>').show();
            },
            complete: function() {
                // Hide spinner and re-enable button
                $saveButton.prop('disabled', false);
                $spinner.css('visibility', 'hidden');
            }
        });
    });
});
