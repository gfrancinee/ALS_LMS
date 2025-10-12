document.addEventListener('DOMContentLoaded', () => {

    // --- LOGIC FOR DYNAMIC QUESTION FORM ---
    const questionTypeSelect = document.getElementById('question_type');

    // Get references to all the input fields that might be required
    const mcOptionRadios = document.querySelectorAll('input[name="correct_option"]');
    const mcOptionInputs = document.querySelectorAll('#multiple-choice-fields input[name="options[]"]');
    const tfOptionRadios = document.querySelectorAll('input[name="tf_correct_option"]');
    const singleAnswerInput = document.getElementById('single_answer_text');
    const singleAnswerLabel = document.querySelector('#single-answer-fields label');

    const fieldGroups = {
        multiple_choice: document.getElementById('multiple-choice-fields'),
        true_false: document.getElementById('true-false-fields'),
        identification: document.getElementById('single-answer-fields'),
        short_answer: document.getElementById('single-answer-fields'),
        essay: document.getElementById('single-answer-fields')
    };

    const updateVisibleFields = () => {
        const selectedType = questionTypeSelect.value;

        // --- Step 1: Hide all groups and remove 'required' from all inputs ---
        for (const key in fieldGroups) {
            if (fieldGroups[key]) fieldGroups[key].style.display = 'none';
        }
        mcOptionRadios.forEach(el => el.required = false);
        mcOptionInputs.forEach(el => el.required = false);
        tfOptionRadios.forEach(el => el.required = false);
        singleAnswerInput.required = false;

        // --- Step 2: Show the correct group ---
        if (fieldGroups[selectedType]) {
            fieldGroups[selectedType].style.display = 'block';
        }

        // --- Step 3: Set 'required' status and labels for the visible group ---
        if (selectedType === 'multiple_choice') {
            mcOptionRadios.forEach(el => el.required = true);
            mcOptionInputs[0].required = true;
            mcOptionInputs[1].required = true;
        } else if (selectedType === 'true_false') {
            tfOptionRadios.forEach(el => el.required = true);
        } else if (['identification', 'short_answer', 'essay'].includes(selectedType)) {
            // The label is now always the same for these types
            singleAnswerLabel.textContent = 'Correct Answer (Optional):';
            // But the input is only required for 'identification'
            if (selectedType === 'identification') {
                singleAnswerInput.required = true;
            }
        }
    };

    if (questionTypeSelect) {
        questionTypeSelect.addEventListener('change', updateVisibleFields);
        updateVisibleFields(); // Run once on page load
    }

    // --- LOGIC FOR ADDING A QUESTION (AJAX SUBMISSION) ---
    const addQuestionForm = document.getElementById('add-question-form');
    if (addQuestionForm) {
        addQuestionForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(addQuestionForm);
            const submitButton = addQuestionForm.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Adding...';

            try {
                const response = await fetch('/ALS_LMS/ajax/add_question.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + (result.error || 'Could not add question.'));
                }
            } catch (error) {
                console.error('Submission failed:', error);
                alert('A network or server error occurred. Please try again.');
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'Add Question';
            }
        });
    }

    // --- LOGIC FOR EDITING A QUESTION ---
    const editQuestionModal = document.getElementById('editQuestionModal');
    const editForm = document.getElementById('edit-question-form');
    const loader = document.getElementById('edit-question-loader');
    const answerContainer = document.getElementById('edit-answer-fields-container');

    if (editQuestionModal) {
        editQuestionModal.addEventListener('show.bs.modal', async (event) => {
            // Show loader, hide form
            loader.style.display = 'block';
            editForm.style.display = 'none';
            answerContainer.innerHTML = ''; // Clear previous content

            const button = event.relatedTarget;
            const questionId = button.getAttribute('data-question-id');
            document.getElementById('edit_question_id').value = questionId;

            try {
                const response = await fetch(`/ALS_LMS/ajax/get_question_details.php?question_id=${questionId}`);
                const result = await response.json();

                if (result.success) {
                    const { question, options } = result;

                    // Populate main question fields
                    document.getElementById('edit_question_text').value = question.question_text;
                    document.getElementById('edit_question_type').value = question.question_type;

                    // Build the answer fields based on question type
                    let html = '';
                    if (question.question_type === 'multiple_choice') {
                        html += '<label class="form-label fw-bold">Options (Select the correct answer):</label>';
                        options.forEach((opt, index) => {
                            const checked = opt.is_correct == 1 ? 'checked' : '';
                            html += `
                                <div class="input-group mb-2">
                                    <div class="input-group-text">
                                        <input class="form-check-input mt-0 form-check-input-success" type="radio" name="correct_option" value="${index}" ${checked} required>
                                    </div>
                                    <input type="text" class="form-control" name="options[]" value="${opt.option_text}" required>
                                </div>`;
                        });
                    } else if (question.question_type === 'true_false') {
                        html += '<label class="form-label fw-bold">Options (Select the correct answer):</label>';
                        options.forEach((opt, index) => {
                            const checked = opt.is_correct == 1 ? 'checked' : '';
                            html += `
                                <div class="input-group mb-2">
                                    <div class="input-group-text">
                                        <input class="form-check-input mt-0 form-check-input-success" type="radio" name="tf_correct_option" value="${index}" ${checked} required>
                                    </div>
                                    <input type="text" class="form-control" name="tf_options[]" value="${opt.option_text}" readonly>
                                </div>`;
                        });
                    } else if (['identification', 'short_answer', 'essay'].includes(question.question_type)) {
                        const answer = options[0]?.option_text || '';
                        const required = question.question_type === 'identification' ? 'required' : '';
                        html += `
                            <label for="edit_single_answer_text" class="form-label fw-bold">Correct Answer (Optional):</label>
                            <input type="text" class="form-control" id="edit_single_answer_text" name="single_answer_text" value="${answer}" ${required}>`;
                    }
                    answerContainer.innerHTML = html;

                    // Hide loader, show form
                    loader.style.display = 'none';
                    editForm.style.display = 'block';
                } else {
                    alert('Error: ' + result.error);
                    bootstrap.Modal.getInstance(editQuestionModal).hide();
                }
            } catch (error) {
                console.error('Failed to fetch question details:', error);
                alert('An error occurred while loading question data.');
                bootstrap.Modal.getInstance(editQuestionModal).hide();
            }
        });
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

    // --- LOGIC FOR DELETING A QUESTION ---
    const deleteQuestionModal = document.getElementById('deleteQuestionModal');
    const deleteQuestionForm = document.getElementById('delete-question-form');
    const deleteQuestionIdInput = document.getElementById('delete_question_id');
    let questionCardToDelete = null;

    if (deleteQuestionModal) {
        // When the modal is about to be shown...
        deleteQuestionModal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget; // The delete icon button that was clicked
            const questionId = button.getAttribute('data-question-id');

            // Set the question ID in the form's hidden input
            deleteQuestionIdInput.value = questionId;

            // Find the parent .card element to remove it from the page on success
            questionCardToDelete = button.closest('.card');
        });
    }

    if (deleteQuestionForm) {
        // When the "Yes, Delete" button in the modal is clicked...
        deleteQuestionForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(deleteQuestionForm);
            const submitButton = deleteQuestionForm.querySelector('button[type="submit"]');
            submitButton.disabled = true;

            try {
                const response = await fetch('/ALS_LMS/ajax/delete_question.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    // Hide the modal
                    const modal = bootstrap.Modal.getInstance(deleteQuestionModal);
                    modal.hide();

                    // Remove the question's card from the page without a reload
                    if (questionCardToDelete) {
                        questionCardToDelete.remove();
                    }
                } else {
                    alert('Error: ' + (result.error || 'Could not delete question.'));
                }
            } catch (error) {
                console.error('Deletion failed:', error);
                alert('An error occurred. Please try again.');
            } finally {
                submitButton.disabled = false;
            }
        });
    }

});