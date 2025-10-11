document.addEventListener('DOMContentLoaded', () => {

    // --- LOGIC FOR DYNAMIC QUESTION FORM ---
    const questionTypeSelect = document.getElementById('question_type');

    const fieldGroups = {
        multiple_choice: document.getElementById('multiple-choice-fields'),
        true_false: document.getElementById('true-false-fields'),
        identification: document.getElementById('single-answer-fields'),
        short_answer: document.getElementById('single-answer-fields'),
        essay: document.getElementById('single-answer-fields')
    };

    const singleAnswerLabel = document.querySelector('#single-answer-fields label');
    const singleAnswerInput = document.getElementById('single_answer_text');

    const updateVisibleFields = () => {
        const selectedType = questionTypeSelect.value;

        // Hide all field groups
        for (const key in fieldGroups) {
            if (fieldGroups[key]) fieldGroups[key].style.display = 'none';
        }

        // Show the selected one
        if (fieldGroups[selectedType]) {
            fieldGroups[selectedType].style.display = 'block';
        }

        // Update label and requirement for single answer field
        if (selectedType === 'identification') {
            singleAnswerLabel.textContent = 'Correct Answer:';
            singleAnswerInput.required = true;
        } else {
            singleAnswerLabel.textContent = 'Correct Answer (Optional):';
            singleAnswerInput.required = false;
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
                const response = await fetch('../ajax/add_question.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    alert('Question added successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + (result.error || 'Could not add question.'));
                }
            } catch (error) {
                console.error('Submission failed:', error);
                alert('An error occurred. Please try again.');
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'Add Question';
            }
        });
    }

    // --- LOGIC FOR DELETING A QUESTION ---
    // (Your existing delete question code goes here)

});