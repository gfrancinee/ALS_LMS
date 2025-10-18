document.addEventListener('DOMContentLoaded', () => {

    // --- COMMON VARIABLES ---
    const currentAssessmentId = document.querySelector('input[name="assessment_id"]')?.value || new URLSearchParams(window.location.search).get('id');
    const questionListContainer = document.getElementById('question-list');

    // --- LOGIC FOR DYNAMIC QUESTION FORM (Your Original Code - Unchanged) ---
    const questionTypeSelect = document.getElementById('question_type');
    const mcOptionRadios = document.querySelectorAll('input[name="correct_option"]');
    const mcOptionInputs = document.querySelectorAll('#multiple-choice-fields input[name="options[]"]');
    const tfOptionRadios = document.querySelectorAll('input[name="tf_correct_option"]');
    const singleAnswerInput = document.getElementById('single_answer_text');
    const singleAnswerLabel = document.querySelector('#single-answer-fields label');

    const fieldGroups = {
        multiple_choice: document.getElementById('multiple-choice-fields'),
        true_false: document.getElementById('true-false-fields'),
        identification: document.getElementById('single-answer-fields'),
        short_answer: document.getElementById('single-answer-fields'), // Assuming short_answer uses single-answer fields too
        essay: document.getElementById('essay-fields') // Use essay-fields div
    };

    // Added elements for grading
    const gradingArea = document.getElementById('gradingArea');
    const gradingAutoRadio = document.getElementById('gradingAuto');
    const gradingManualRadio = document.getElementById('gradingManual');
    const pointsGroup = document.getElementById('pointsGroup');
    const maxPointsInput = document.getElementById('maxPoints');
    const gradingHelpText = document.getElementById('gradingHelpText');


    const updateVisibleFields = () => {
        const selectedType = questionTypeSelect.value;

        // Hide all specific answer/grading groups first
        for (const key in fieldGroups) {
            if (fieldGroups[key]) fieldGroups[key].style.display = 'none';
        }
        if (gradingArea) gradingArea.style.display = 'none'; // Hide grading initially
        if (pointsGroup) pointsGroup.style.display = 'none'; // Hide points initially

        // Remove 'required' from inputs that might become hidden
        mcOptionRadios.forEach(el => el.required = false);
        mcOptionInputs.forEach(el => el.required = false);
        tfOptionRadios.forEach(el => el.required = false);
        if (singleAnswerInput) singleAnswerInput.required = false;


        // Show the correct answer group
        if (fieldGroups[selectedType]) {
            fieldGroups[selectedType].style.display = 'block';
        }

        // Show grading options and set defaults/requirements
        if (gradingArea) {
            gradingArea.style.display = 'block';
            gradingAutoRadio.checked = true; // Default to automatic
            gradingManualRadio.disabled = false; // Enable manual by default
            gradingHelpText.textContent = 'Automatic grading is based on the options/answer provided above.';

            // Set required status and specific grading logic based on type
            if (selectedType === 'multiple_choice') {
                mcOptionRadios.forEach(el => el.required = true);
                mcOptionInputs[0].required = true; // First two MC options required
                mcOptionInputs[1].required = true;
            } else if (selectedType === 'true_false') {
                tfOptionRadios.forEach(el => el.required = true);
            } else if (selectedType === 'identification') {
                if (singleAnswerInput) singleAnswerInput.required = true; // Only ID requires an answer for auto-grading
                if (singleAnswerLabel) singleAnswerLabel.textContent = 'Correct Answer:';
                gradingHelpText.textContent = 'Automatic grading is case-insensitive. Leave blank or choose Manual if multiple answers are okay.';
            } else if (selectedType === 'short_answer') {
                if (singleAnswerLabel) singleAnswerLabel.textContent = 'Correct Answer (Optional):';
                gradingHelpText.textContent = 'Automatic grading is case-insensitive. Leave blank or choose Manual if multiple answers or partial credit is needed.';
            } else if (selectedType === 'essay') {
                gradingManualRadio.disabled = true; // Force manual for essay
                gradingManualRadio.checked = true; // Select manual for essay
                if (pointsGroup) pointsGroup.style.display = 'block'; // Show points for manual
                if (maxPointsInput) maxPointsInput.value = maxPointsInput.value || 1; // Ensure points has a value
                gradingHelpText.textContent = 'Essay questions require manual grading.';
            }
            togglePointsInput(); // Update points visibility based on initial state
        }
    };

    // Function to toggle points input based on grading type selection
    const togglePointsInput = () => {
        if (!gradingManualRadio || !pointsGroup || !maxPointsInput || !gradingHelpText || !questionTypeSelect) return;

        if (gradingManualRadio.checked) {
            pointsGroup.style.display = 'block';
            maxPointsInput.value = maxPointsInput.value || 1; // Default to 1 if empty
            gradingHelpText.textContent = 'Specify the maximum points for this manually graded question.';
        } else {
            pointsGroup.style.display = 'none';
            maxPointsInput.value = 1; // Reset to 1 for automatic
            // Update help text based on question type when switching back to Auto
            const selectedType = questionTypeSelect.value;
            if (selectedType === 'identification') {
                gradingHelpText.textContent = 'Automatic grading is case-insensitive. Leave blank or choose Manual if multiple answers are okay.';
            } else if (selectedType === 'short_answer') {
                gradingHelpText.textContent = 'Automatic grading is case-insensitive. Leave blank or choose Manual if multiple answers or partial credit is needed.';
            } else if (selectedType === 'essay') {
                gradingHelpText.textContent = 'Essay questions require manual grading.';
            } else { // MC, TF
                gradingHelpText.textContent = 'Automatic grading is based on the options/answer provided above.';
            }
        }
    }


    if (questionTypeSelect) {
        questionTypeSelect.addEventListener('change', updateVisibleFields);
        updateVisibleFields(); // Run once on page load
    }
    // Add listeners for grading radio buttons
    if (gradingAutoRadio) gradingAutoRadio.addEventListener('change', togglePointsInput);
    if (gradingManualRadio) gradingManualRadio.addEventListener('change', togglePointsInput);

    // --- LOGIC FOR ADDING A QUESTION (AJAX SUBMISSION - NO RELOAD) ---
    const addQuestionForm = document.getElementById('add-question-form');

    if (addQuestionForm && questionListContainer) { // Check if form and list container exist
        addQuestionForm.addEventListener('submit', async (e) => {
            e.preventDefault(); // Prevent default page reload

            const formData = new FormData(addQuestionForm);
            const submitButton = addQuestionForm.querySelector('button[type="submit"]');

            // Basic client-side validation (optional, but good practice)
            const questionText = formData.get('question_text');
            if (!questionText || questionText.trim() === '') {
                alert('Please enter the question text.');
                return;
            }
            // Add more validation as needed for options, etc.

            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Adding...';

            try {
                // Use the form's action attribute as the URL
                const response = await fetch(addQuestionForm.action, {
                    method: 'POST',
                    body: formData
                });

                // Check response type before parsing JSON
                const contentType = response.headers.get("content-type");
                if (!response.ok || !contentType || !contentType.includes("application/json")) {
                    const text = await response.text();
                    throw new Error(`Server error (${response.status}) or invalid response. Content: ${text}`);
                }

                const result = await response.json();

                if (result.success && result.newQuestionHtml) {
                    // Remove "No questions" message if it exists
                    document.getElementById('no-questions-message')?.remove();

                    // Append the new question HTML returned by the server
                    questionListContainer.insertAdjacentHTML('beforeend', result.newQuestionHtml);

                    // Reset the form fields
                    addQuestionForm.reset();

                    // Reset the dynamic visibility of form fields (assuming functions are defined)
                    if (typeof updateVisibleFields === 'function') {
                        updateVisibleFields(); // Reset fields display to default (e.g., Multiple Choice)
                    }
                    if (typeof togglePointsInput === 'function') {
                        togglePointsInput(); // Reset grading display
                    }

                    // Hide the collapsible form section
                    const collapseElement = document.getElementById('addNewQuestionForm');
                    if (collapseElement) {
                        // Get existing instance or create one, then hide
                        const bsCollapse = bootstrap.Collapse.getInstance(collapseElement) || new bootstrap.Collapse(collapseElement, { toggle: false });
                        bsCollapse.hide();
                    }

                    // Optional: Attach event listeners to buttons within the new HTML if needed
                    // (Often not necessary if using event delegation)

                } else {
                    // Display specific error from server if available
                    alert('Error adding question: ' + (result.error || 'Unknown error occurred. Please check server logs.'));
                }
            } catch (error) {
                console.error('Submission failed:', error);
                // Provide more specific error message
                alert(`A network or server error occurred: ${error.message}. Please try again.`);
            } finally {
                submitButton.disabled = false;
                // Make sure the text matches the button in the HTML
                submitButton.textContent = 'Add Question';
            }
        });
    } else {
        if (!addQuestionForm) console.error("Add question form not found.");
        if (!questionListContainer) console.error("Question list container not found.");
    }

    // --- LOGIC FOR EDITING A QUESTION (Complete Fetch & Submit) ---
    const editQuestionModalEl = document.getElementById('editQuestionModal');
    const editForm = document.getElementById('edit-question-form');
    const editLoader = document.getElementById('edit-question-loader');
    const editAnswerContainer = document.getElementById('edit-answer-fields-container');
    // Ensure questionListContainer is defined earlier in the script and is correct

    if (editQuestionModalEl && editForm && editLoader && editAnswerContainer && questionListContainer) {

        // Helper function to toggle points input visibility in EDIT modal
        function toggleEditPointsInput() {
            const editPointsGroup = document.getElementById('editPointsGroup');
            const editGradingManualRadio = document.getElementById('editGradingManual');
            if (editGradingManualRadio && editPointsGroup) {
                editPointsGroup.style.display = editGradingManualRadio.checked ? 'block' : 'none';
            }
        }

        // Helper function to safely encode HTML entities for input values
        function htmlspecialchars(str) {
            if (typeof str !== 'string') return '';
            return str.replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }


        // Event listener: When the edit modal is about to be shown (Your Provided Code, Verified)
        editQuestionModalEl.addEventListener('show.bs.modal', async (event) => {
            // Initial state: Show loader, hide form, clear old options
            editLoader.style.display = 'block';
            editLoader.innerHTML = '<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>';
            editForm.style.display = 'none';
            editAnswerContainer.innerHTML = '';

            const button = event.relatedTarget; // The edit button clicked
            if (!button) {
                console.error("Could not find button that triggered edit modal.");
                editLoader.innerHTML = '<p class="text-danger">Error: Could not identify question.</p>';
                return;
            }
            const questionId = button.getAttribute('data-question-id');
            const hiddenInput = document.getElementById('edit_question_id');
            if (hiddenInput) {
                hiddenInput.value = questionId;
            } else {
                console.error("Hidden input #edit_question_id not found in modal.");
                editLoader.innerHTML = '<p class="text-danger">Internal error: Form setup incorrect.</p>';
                return;
            }

            try {
                // Fetch existing question details using '?id='
                const response = await fetch(`/ALS_LMS/ajax/get_question_details.php?id=${questionId}`);

                // Response Validation
                if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    const text = await response.text();
                    throw new TypeError(`Expected JSON, got ${contentType}. Content: ${text}`);
                }
                const result = await response.json();

                // Check Response Structure - Expecting {"success": true, "data": {"question": {...}, "options": [...]}}
                if (result.success && result.data && result.data.question) {
                    const q = result.data.question;
                    const options = result.data.options || [];

                    // Populate Form Fields
                    document.getElementById('edit_question_text').value = q.question_text;
                    document.getElementById('edit_question_type').value = q.question_type;

                    // Populate grading info
                    if (q.grading_type !== undefined && q.max_points !== undefined) {
                        document.getElementById(q.grading_type === 'manual' ? 'editGradingManual' : 'editGradingAuto').checked = true;
                        document.getElementById('editMaxPoints').value = q.max_points;
                    } else {
                        document.getElementById('editGradingAuto').checked = true;
                        document.getElementById('editMaxPoints').value = 1;
                    }
                    toggleEditPointsInput(); // Adjust points field visibility

                    // Build Dynamic Answer Fields
                    let html = '';
                    if (q.question_type === 'multiple_choice') {
                        html += '<label class="form-label fw-bold">Options (Select the correct answer):</label>';
                        options.forEach((opt) => {
                            const isChecked = opt.is_correct == 1 ? 'checked' : '';
                            html += `
                            <div class="input-group mb-2">
                                <div class="input-group-text">
                                    <input class="form-check-input mt-0 form-check-input-success" type="radio" name="edit_correct_option" value="${opt.id}" ${isChecked} required>
                                </div>
                                <input type="text" class="form-control" name="edit_options[${opt.id}]" value="${htmlspecialchars(opt.option_text)}" required>
                                <input type="hidden" name="edit_option_ids[]" value="${opt.id}">
                            </div>`;
                        });
                    } else if (q.question_type === 'true_false') {
                        html += '<label class="form-label fw-bold">Options (Select the correct answer):</label>';
                        options.forEach((opt) => {
                            const isChecked = opt.is_correct == 1 ? 'checked' : '';
                            html += `
                            <div class="input-group mb-2">
                                <div class="input-group-text">
                                    <input class="form-check-input mt-0 form-check-input-success" type="radio" name="edit_tf_correct_option" value="${opt.id}" ${isChecked} required>
                                </div>
                                <input type="text" class="form-control" name="edit_options[${opt.id}]" value="${htmlspecialchars(opt.option_text)}" readonly>
                                <input type="hidden" name="edit_option_ids[]" value="${opt.id}">
                            </div>`;
                        });
                    } else if (q.question_type === 'identification' || q.question_type === 'short_answer') {
                        const correctAnswer = options.find(opt => opt.is_correct == 1);
                        html += `
                        <label for="edit_single_answer_text" class="form-label fw-bold">Correct Answer:</label>
                        <input type="text" class="form-control" id="edit_single_answer_text" name="edit_single_answer_text" value="${correctAnswer ? htmlspecialchars(correctAnswer.option_text) : ''}">
                        <div class="form-text">Used for automatic grading (case-insensitive).</div>`;
                        if (correctAnswer) {
                            html += `<input type="hidden" name="edit_option_ids[]" value="${correctAnswer.id}">`;
                        } else {
                            html += `<input type="hidden" name="edit_option_ids[]" value="new">`;
                        }
                    } else if (q.question_type === 'essay') {
                        html += '<p class="text-muted fst-italic">Essay questions require manual grading.</p>';
                    }
                    editAnswerContainer.innerHTML = html;

                    // Final State: Hide loader, show form
                    editLoader.style.display = 'none';
                    editForm.style.display = 'block';

                } else {
                    throw new Error(result.error || 'Invalid data structure received from server.');
                }
            } catch (error) {
                // Error Handling
                console.error('Failed to fetch question details:', error);
                editLoader.innerHTML = `<p class="text-danger">Error loading question data: ${error.message}. Please close and try again.</p>`;
                editForm.style.display = 'none';
            }
        });

        // Add listeners for grading type change in EDIT modal
        document.getElementById('editGradingAuto')?.addEventListener('change', toggleEditPointsInput);
        document.getElementById('editGradingManual')?.addEventListener('change', toggleEditPointsInput);


        // --- *** NEWLY ADDED/RESTORED *** ---
        // Event listener: When the edit form is submitted
        editForm.addEventListener('submit', async (e) => {
            e.preventDefault(); // Stop default submission
            const formData = new FormData(editForm);
            const submitButton = editQuestionModalEl.querySelector('.modal-footer button[type="submit"]');

            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

            try {
                // Send updated data to the server using the form's action URL
                const response = await fetch(editForm.action, {
                    method: 'POST',
                    body: formData
                });

                // Response Validation
                if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    const text = await response.text();
                    throw new TypeError(`Expected JSON, got ${contentType}. Content: ${text}`);
                }
                const result = await response.json();

                // Check Response Structure - Expecting {"success": true, "updatedQuestion": {...}}
                if (result.success && result.updatedQuestion) {
                    // Update Display on Main Page
                    const questionId = result.updatedQuestion.id;
                    const questionCard = questionListContainer.querySelector(`.question-card[data-question-id="${questionId}"]`);
                    if (questionCard) {
                        // Update text
                        questionCard.querySelector('.question-text-display').innerHTML = result.updatedQuestion.question_text.replace(/\n/g, '<br>');
                        // Update grading badge
                        const gradingBadge = questionCard.querySelector('.badge.bg-info');
                        if (gradingBadge) {
                            gradingBadge.textContent = `${result.updatedQuestion.grading_type.charAt(0).toUpperCase() + result.updatedQuestion.grading_type.slice(1)} Grading (${result.updatedQuestion.max_points}pt${result.updatedQuestion.max_points > 1 ? 's' : ''})`;
                        }
                    } else {
                        console.warn("Could not find question card to update on page for ID:", questionId);
                        // Optionally reload if dynamic update fails
                        // window.location.reload();
                    }

                    // Close Modal
                    const modalInstance = bootstrap.Modal.getInstance(editQuestionModalEl);
                    modalInstance.hide();
                } else {
                    alert('Error saving changes: ' + (result.error || 'Unknown error occurred. Please check server logs.'));
                }
            } catch (error) {
                console.error('Save failed:', error);
                alert(`An error occurred while saving: ${error.message}. Please try again.`);
            } finally {
                // Reset Button
                submitButton.disabled = false;
                submitButton.textContent = 'Save Changes';
            }
        });
        // --- *** END OF ADDED/RESTORED SUBMIT LOGIC *** ---

    } else {
        // Log errors if essential elements for editing are missing on page load
        if (!editQuestionModalEl) console.error("Edit Question Modal element (#editQuestionModal) not found.");
        if (!editForm) console.error("Edit Question Form element (#edit-question-form) not found.");
        if (!editLoader) console.error("Edit Question Loader element (#edit-question-loader) not found.");
        if (!editAnswerContainer) console.error("Edit Answer Container element (#edit-answer-fields-container) not found.");
        if (!questionListContainer) console.error("Main Question List Container element (#question-list) not found for updates.");
    }

    // --- LOGIC FOR SAVING EDITED QUESTION ---
    if (editForm) {
        editForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(editForm);
            const submitButton = document.querySelector('#editQuestionModal button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

            try {
                const response = await fetch('/ALS_LMS/ajax/update_question.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    const questionId = formData.get('question_id');

                    // Find the question card on the main page
                    const questionCard = document.querySelector(`button[data-question-id="${questionId}"]`).closest('.card');
                    if (questionCard) {
                        // Update the question text directly on the page
                        const questionTextElement = questionCard.querySelector('.card-body p:nth-of-type(2)');
                        questionTextElement.textContent = result.updated_text;
                    }

                    // Hide the modal
                    const modal = bootstrap.Modal.getInstance(editQuestionModal);
                    modal.hide();
                } else {
                    alert('Error: ' + (result.error || 'Could not save changes.'));
                }
            } catch (error) {
                console.error('Save failed:', error);
                alert('An error occurred. Please try again.');
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'Save Changes';
            }
        });
    }

    // --- LOGIC FOR DELETING/REMOVING A QUESTION (Corrected Submission) ---
    const deleteQuestionModalEl = document.getElementById('deleteQuestionModal');
    // Make sure your form ID in the HTML matches this ID
    const deleteQuestionForm = document.getElementById('remove-question-form');
    const deleteQuestionIdInput = document.getElementById('remove_question_id'); // Ensure hidden input ID matches

    if (deleteQuestionModalEl && deleteQuestionIdInput) {
        // When the modal is about to be shown...
        deleteQuestionModalEl.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget; // The delete icon button that was clicked
            if (button) {
                const questionId = button.getAttribute('data-question-id');
                // Set the question ID in the form's hidden input
                deleteQuestionIdInput.value = questionId;
            } else {
                console.error("Could not find the button that triggered the delete modal.");
            }
        });
    } else {
        console.error("Delete modal or hidden question ID input not found.");
    }

    if (deleteQuestionForm) {
        // When the "Yes, Remove" button in the modal is clicked...
        deleteQuestionForm.addEventListener('submit', async (e) => {
            e.preventDefault(); // Prevent default form submission

            // Explicitly get the values needed
            const questionId = deleteQuestionIdInput.value;
            // Find the assessment_id input within this specific form
            const assessmentIdInput = deleteQuestionForm.querySelector('input[name="assessment_id"]');
            const assessmentId = assessmentIdInput ? assessmentIdInput.value : null;

            const submitButton = deleteQuestionForm.querySelector('button[type="submit"]');

            // Check if we have the necessary IDs
            if (!questionId || !assessmentId) {
                alert('Error: Could not get question or assessment ID. Cannot remove.');
                console.error("Missing IDs:", { questionId, assessmentId });
                return; // Stop if IDs are missing
            }

            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Removing...';

            // Create FormData MANUALLY to ensure data is correct
            const formData = new FormData();
            formData.append('question_id', questionId);
            formData.append('assessment_id', assessmentId);

            console.log("Sending data to:", deleteQuestionForm.action);
            console.log("FormData:", { question_id: questionId, assessment_id: assessmentId });


            try {
                // Use the form's action attribute for the URL
                const response = await fetch(deleteQuestionForm.action, {
                    method: 'POST',
                    body: formData // Send the manually created FormData
                });

                // Check response type before parsing JSON
                const contentType = response.headers.get("content-type");
                if (!response.ok || !contentType || !contentType.includes("application/json")) {
                    const text = await response.text();
                    throw new TypeError(`Server error (${response.status}) or invalid response. Content: ${text}`);
                }

                const result = await response.json();
                console.log("Server response:", result); // Log server response

                if (result.success) {
                    const modal = bootstrap.Modal.getInstance(deleteQuestionModalEl);
                    modal.hide();

                    // Find the correct card to remove using data-question-id
                    const questionCardToRemove = document.querySelector(`.question-card[data-question-id="${questionId}"]`);
                    if (questionCardToRemove) {
                        console.log("Removing card:", questionCardToRemove);
                        questionCardToRemove.remove();
                    } else {
                        console.warn("Could not find question card to remove for ID:", questionId);
                    }


                    // Show "No questions" message if list is empty
                    const questionList = document.getElementById('question-list'); // Get the container
                    if (questionList && !questionList.querySelector('.question-card')) { // Check if any card remains
                        if (!document.getElementById('no-questions-message')) {
                            questionList.innerHTML = '<p id="no-questions-message" class="text-muted">No questions have been added to this assessment yet.</p>';
                        }
                    }

                } else {
                    alert('Error removing question: ' + (result.error || 'Could not remove question link.'));
                }
            } catch (error) {
                console.error('Removal failed:', error);
                // Provide more specific error feedback
                alert(`An error occurred: ${error.message}. Please try again.`);
            } finally {
                submitButton.disabled = false;
                // Make sure button text matches HTML
                submitButton.textContent = 'Remove';
            }
        });
    } else {
        console.error("Delete form element not found.");
    }


    // --- START: NEW QUESTION BANK MODAL LOGIC ---
    const questionBankModalEl = document.getElementById('questionBankModal');
    const questionBankListContainer = document.getElementById('questionBankListContainer');
    const searchInput = document.getElementById('questionBankSearch');
    const typeFilter = document.getElementById('questionBankTypeFilter');
    const addSelectedBtn = document.getElementById('addSelectedQuestionsBtn');

    let debounceTimer;

    // Function to fetch questions from the bank
    async function loadQuestionsFromBank() {
        // Ensure elements exist before proceeding
        if (!questionBankListContainer || !currentAssessmentId) {
            console.error("Question Bank container or Assessment ID not found.");
            if (questionBankListContainer) questionBankListContainer.innerHTML = '<p class="text-center text-danger">Error: Could not determine Assessment ID.</p>';
            return;
        }
        questionBankListContainer.innerHTML = '<p class="text-center text-muted"><span class="spinner-border spinner-border-sm"></span> Loading questions...</p>'; // Show loading state

        const searchTerm = searchInput ? searchInput.value : '';
        const filterType = typeFilter ? typeFilter.value : '';

        // Construct URL with parameters
        const url = `../ajax/get_question_bank.php?assessment_id=${currentAssessmentId}&search=${encodeURIComponent(searchTerm)}&type=${encodeURIComponent(filterType)}`;

        try {
            const response = await fetch(url);
            // Check if response is ok and is JSON
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                const text = await response.text();
                throw new TypeError(`Expected JSON, got ${contentType}. Content: ${text}`);
            }

            const result = await response.json();

            questionBankListContainer.innerHTML = ''; // Clear loading state

            if (result.success && result.data.length > 0) {
                result.data.forEach(q => {
                    const div = document.createElement('div');
                    div.className = 'form-check border-bottom pb-2 mx-2';
                    // Added data-text attribute for potential future features
                    div.innerHTML = `
                    <input class="form-check-input question-bank-checkbox" type="checkbox" name="selected_questions[]" value="${q.id}" id="qbank_${q.id}" data-text="${q.question_text}">
                    <label class="form-check-label w-100" for="qbank_${q.id}">
                        <div class="d-flex justify-content-between">
                            <span class="question-text">${q.question_text.length > 150 ? q.question_text.substring(0, 150) + '...' : q.question_text}</span>
                            <span class="badge text-dark ms-2">${q.question_type.replace('_', ' ')}</span>
                        </div>
                    </label>
                `;
                    questionBankListContainer.appendChild(div);
                });
            } else if (result.success) {
                questionBankListContainer.innerHTML = '<p class="text-center text-muted">No questions found matching your criteria, or all available questions are already added.</p>';
            } else {
                questionBankListContainer.innerHTML = `<p class="text-center text-danger">Error loading questions: ${result.error || 'Unknown error'}</p>`;
            }
        } catch (error) {
            console.error("Error loading question bank:", error);
            questionBankListContainer.innerHTML = `<p class="text-center text-danger">Could not load questions. ${error.message}</p>`;
        }
    }

    // Event listener for when the modal is shown
    if (questionBankModalEl) {
        questionBankModalEl.addEventListener('show.bs.modal', () => {
            if (searchInput) searchInput.value = ''; // Clear search on open
            if (typeFilter) typeFilter.value = ''; // Reset filter on open
            loadQuestionsFromBank(); // Load initial questions
        });

        // Event listeners for search and filter (with debounce for search)
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(loadQuestionsFromBank, 500); // Wait 500ms after typing
            });
        }
        if (typeFilter) {
            typeFilter.addEventListener('change', loadQuestionsFromBank);
        }

        // Event listener for the "Add Selected Questions" button
        if (addSelectedBtn) {
            addSelectedBtn.addEventListener('click', async () => {
                const selectedCheckboxes = questionBankListContainer.querySelectorAll('.question-bank-checkbox:checked');
                const selectedQuestionIds = Array.from(selectedCheckboxes).map(cb => cb.value);

                if (selectedQuestionIds.length === 0) {
                    alert('Please select at least one question to add.');
                    return;
                }

                addSelectedBtn.disabled = true;
                addSelectedBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...';

                const formData = new FormData();
                formData.append('assessment_id', currentAssessmentId);
                selectedQuestionIds.forEach(id => formData.append('question_ids[]', id));

                try {
                    // IMPORTANT: Ensure this PHP file exists and works
                    const response = await fetch('../ajax/add_questions_to_assessment.php', {
                        method: 'POST',
                        body: formData
                    });
                    // Check response type before parsing JSON
                    const contentType = response.headers.get("content-type");
                    if (!response.ok || !contentType || !contentType.includes("application/json")) {
                        const text = await response.text();
                        throw new TypeError(`Server error (${response.status}) or invalid response. Content: ${text}`);
                    }

                    const result = await response.json();

                    if (result.success && result.addedQuestionsHtml) {
                        // Make sure the main question list container exists on the page
                        const mainQuestionList = document.getElementById('question-list');
                        if (mainQuestionList) {
                            mainQuestionList.querySelector('#no-questions-message')?.remove(); // Remove 'no questions' message
                            mainQuestionList.insertAdjacentHTML('beforeend', result.addedQuestionsHtml); // Add new questions
                        } else {
                            console.error("Could not find '#question-list' container to add questions to.");
                            // Optionally reload if dynamic update fails
                            // window.location.reload();
                        }
                        // Close the modal
                        const modalInstance = bootstrap.Modal.getInstance(questionBankModalEl);
                        modalInstance.hide();
                    } else {
                        alert('Error adding questions: ' + (result.error || 'Unknown error'));
                    }

                } catch (error) {
                    console.error("Error adding questions from bank:", error);
                    alert(`An error occurred while adding the questions: ${error.message}`);
                } finally {
                    addSelectedBtn.disabled = false;
                    addSelectedBtn.textContent = 'Add Selected Questions';
                }
            });
        }
    } else {
        console.warn("Question Bank Modal element not found."); // Warning if modal doesn't exist
    }
    // --- END: NEW QUESTION BANK MODAL LOGIC ---

});