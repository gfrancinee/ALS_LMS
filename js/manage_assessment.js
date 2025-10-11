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

    // --- LOGIC FOR DELETING A QUESTION ---
    // (Your existing delete question code should be here)
});