document.addEventListener('DOMContentLoaded', () => {

    // Get the user role from the global variable we set in the PHP file
    const userRole = window.userRole;

    // SECTION 1: ELEMENT REFERENCES
    const strandId = window.strandId;

    // Modals
    const editModal = document.getElementById('editMaterialModal');
    const assessmentModal = document.getElementById('assessmentModal');
    const questionsModal = document.getElementById('questionsModal');
    const participantModal = document.getElementById('participantModal');
    const mediaModal = document.getElementById('mediaModal');

    // Forms, Containers & Alerts
    const editForm = document.querySelector('#editMaterialModal form');
    const assessmentForm = document.getElementById('assessmentForm');
    const assessmentListContainer = document.getElementById('assessmentList');
    const participantListContainer = document.getElementById('participantList');
    const assessmentAlert = document.getElementById('assessmentAlert');
    const questionForm = document.querySelector('#questionsModal form');
    const uploadAlert = document.getElementById('uploadAlert');
    const uploadAlertModal = document.getElementById('uploadAlertModal');

    // Participant Modal Elements
    const addSelectedStudentsBtn = document.getElementById('addSelectedStudentsBtn');
    const studentSearchInput = document.getElementById('studentSearchInput');

    // Material Upload Elements
    const inputContainer = document.getElementById('materialInputContainer');

    // Question Builder Elements
    const formBuilder = document.getElementById("formBuilder");
    const addQuestionBtn = document.getElementById("addQuestionBtn");
    const questionTemplate = document.getElementById("questionTemplate");

    // SECTION 2: FUNCTIONS
    // --- Material Upload Logic ---
    const uploadForm = document.getElementById('uploadForm');
    const materialType = document.getElementById('materialType');
    const dynamicInputArea = document.getElementById('dynamicInputArea');
    const uploadModal = document.getElementById('uploadModal');

    function injectMaterialInput() {
        if (!materialType || !dynamicInputArea) return;
        const type = materialType.value;
        dynamicInputArea.innerHTML = ''; // Clear previous input
        if (type === 'link') {
            dynamicInputArea.innerHTML = `<label for="materialLink" class="form-label">Link URL</label><input type="url" class="form-control" id="materialLink" name="materialLink" required>`;
        } else if (type !== '') {
            dynamicInputArea.innerHTML = `<label for="materialFile" class="form-label">Upload File</label><input type="file" class="form-control" id="materialFile" name="materialFile" required>`;
        }
    }

    if (materialType) materialType.addEventListener('change', injectMaterialInput);
    if (uploadModal) uploadModal.addEventListener('shown.bs.modal', injectMaterialInput);

    if (uploadForm) {
        uploadForm.addEventListener('submit', function (e) {
            e.preventDefault(); // This is the most important line to prevent page refresh

            const uploadAlertModal = document.getElementById('uploadAlertModal');
            const submitButton = this.querySelector('button[type="submit"]');
            const formData = new FormData(this);

            submitButton.disabled = true;
            submitButton.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading...`;
            uploadAlertModal.style.display = 'none';

            fetch('ajax/upload_material.php', {
                method: 'POST',
                body: formData
            })
                .then(res => {
                    if (!res.ok) { throw new Error('Network response was not ok'); }
                    return res.json();
                })
                .then(data => {
                    let alertClass = data.status === 'success' ? 'alert-success' : 'alert-danger';
                    uploadAlertModal.innerHTML = `<div class="alert ${alertClass} mb-0">${data.message}</div>`;
                    uploadAlertModal.style.display = 'block';

                    if (data.status === 'success') {
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        submitButton.disabled = false;
                        submitButton.textContent = 'Upload';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    uploadAlertModal.style.display = 'block';
                    submitButton.disabled = false;
                    submitButton.textContent = 'Upload';
                });
        });
    }

    function getAcceptType(type) {
        switch (type) {
            case 'file': return '.pdf,.doc,.docx,.ppt,.pptx';
            case 'video': return 'video/*';
            case 'image': return 'image/*';
            case 'audio': return 'audio/*';
            default: return '*/*';
        }
    }

    // --- AJAX Refresh Functions ---
    window.refreshMaterialList = function () {
        location.reload();
    };

    // --- Question Builder ---
    if (formBuilder && addQuestionBtn && questionTemplate) {
        function refreshIndices() {
            Array.from(formBuilder.children).forEach((blk, i) => {
                blk.querySelector(".q-index").textContent = i + 1;
            });
        }
        function buildAnswerArea(block, type) {
            const area = block.querySelector(".answer-area");
            area.innerHTML = "";
            if (type === "mcq") {
                for (let i = 0; i < 4; i++) {
                    const opt = document.createElement("div");
                    opt.className = "input-group mb-2";
                    opt.innerHTML = `<span class="input-group-text">${String.fromCharCode(65 + i)}</span><input type="text" class="form-control" name="options[]" placeholder="Option ${i + 1}">`;
                    area.appendChild(opt);
                }
                area.innerHTML += `<label class="form-label mt-2">Correct Answer</label><select class="form-select" name="correct_answer" required><option value="">Select</option><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option></select>`;
            } else if (type === "true_false") {
                area.innerHTML = `<label class="form-label">Correct Answer</label><select class="form-select" name="correct_answer" required><option value="">Select</option><option value="True">True</option><option value="False">False</option></select>`;
            } else if (type === "short_answer" || type === "essay") {
                area.innerHTML = `<label class="form-label">Expected Answer</label><input type="text" class="form-control" name="correct_answer" placeholder="Enter correct answer (optional)">`;
            }
        }
        function addQuestion() {
            const clone = questionTemplate.content.cloneNode(true);
            const block = clone.querySelector(".question-block");
            block.querySelector(".remove-question").addEventListener("click", () => { block.remove(); refreshIndices(); });
            block.querySelector(".move-up").addEventListener("click", () => { if (block.previousElementSibling) { formBuilder.insertBefore(block, block.previousElementSibling); refreshIndices(); } });
            block.querySelector(".move-down").addEventListener("click", () => { if (block.nextElementSibling) { formBuilder.insertBefore(block.nextElementSibling, block); refreshIndices(); } });
            const typeSelect = block.querySelector(".question-type");
            typeSelect.addEventListener("change", () => buildAnswerArea(block, typeSelect.value));
            formBuilder.appendChild(clone);
            refreshIndices();
        }
        addQuestionBtn.addEventListener("click", addQuestion);
    }

    // --- Refresh Assessment List ---
    async function refreshAssessmentList() {
        try {
            // This line requires window.strandId to be set in your strand.php file
            const response = await fetch(`../ajax/get-assessments.php?strand_id=${window.strandId}`);
            if (!response.ok) {
                throw new Error('Network response was not ok.');
            }
            const html = await response.text();
            const assessmentList = document.getElementById('assessmentList');
            if (assessmentList) {
                assessmentList.innerHTML = html;
            }
        } catch (error) {
            console.error('Failed to refresh assessment list:', error);
            const assessmentList = document.getElementById('assessmentList');
            if (assessmentList) {
                assessmentList.innerHTML = '<div class="alert alert-danger">Error loading assessments.</div>';
            }
        }
    }

    // --- Participants Functions ---
    function displayParticipants(participants) {
        const listContainer = document.getElementById('participantList');
        listContainer.innerHTML = '';

        if (!participants || participants.length === 0) {
            listContainer.innerHTML = '<div class="alert alert-info">No participants have been added to this strand yet.</div>';
            return;
        }

        // This loop now runs for BOTH teachers and students
        participants.forEach(participant => {
            let avatarSrc = participant.avatar_url ? `../${participant.avatar_url}` : 'icon';
            const adminLabel = participant.role === 'admin' ? '<span class="badge bg-success ms-2">Admin</span>' : '';

            // The remove button is ONLY created if the user is a teacher
            let removeButtonHtml = '';
            if (window.userRole === 'teacher' && participant.role !== 'admin') {
                removeButtonHtml = `<button class="btn btn-sm btn-outline-danger remove-participant-btn" data-participant-id="${participant.participant_id}"><i class="bi bi-trash"></i> Remove</button>`;
            }

            const participantHtml = `
            <div class="card mb-2 participant-card">
                <div class="card-body d-flex justify-content-between align-items-center py-2">
                    <div class="d-flex align-items-center">
                        ${avatarSrc !== 'icon'
                    ? `<img src="${avatarSrc}" class="rounded-circle me-3" alt="Avatar" width="40" height="40" style="object-fit: cover;">`
                    : `<i class="bi bi-person-circle me-3" style="font-size: 40px; color: #6c757d;"></i>`
                }
                        <div>
                            <h6 class="mb-0">${participant.fname} ${participant.lname} ${adminLabel}</h6>
                        </div>
                    </div>
                    <div>
                        ${removeButtonHtml}
                    </div>
                </div>
            </div>
        `;
            listContainer.insertAdjacentHTML('beforeend', participantHtml);
        });
    }
    async function refreshParticipantList() {
        if (!participantListContainer) return;
        participantListContainer.innerHTML = '<p>Loading participants...</p>';
        try {
            // This path goes up TWO levels from teacher/strand/ to the root ajax folder
            const response = await fetch(`../ajax/get_strand_participants.php?strand_id=${strandId}`);
            const participants = await response.json();
            if (participants.error) throw new Error(participants.error);
            displayParticipants(participants);
        } catch (error) {
            console.error('Failed to refresh participant list:', error);
            participantListContainer.innerHTML = '<p class="text-danger">Could not load participant list.</p>';
        }
    }

    async function loadAvailableStudents() {
        const studentListContainer = document.getElementById('availableStudentsList');
        if (!studentListContainer) return;
        studentListContainer.innerHTML = '<p>Loading students...</p>';
        try {
            // This path goes up TWO levels from teacher/strand/ to the root ajax folder
            const response = await fetch(`../ajax/get_available_students.php?strand_id=${strandId}`);
            const students = await response.json();
            if (students.error) throw new Error(students.error);

            if (students.length === 0) {
                studentListContainer.innerHTML = '<p>No new students are available to add.</p>';
                return;
            }

            let html = students.map(s => `
            <div class="form-check student-item">
                <input class="form-check-input" type="checkbox" value="${s.id}" id="student-${s.id}">
                <label class="form-check-label" for="student-${s.id}">${s.fname} ${s.lname}</label>
            </div>`).join('');
            studentListContainer.innerHTML = html;
        } catch (error) {
            console.error('Failed to load students:', error);
            studentListContainer.innerHTML = '<p class="text-danger">Could not load student list.</p>';
        }
    }

    // 3. The loadAvailableStudents function (this one is likely okay, but included for completeness)
    async function loadAvailableStudents() {
        const studentListContainer = document.getElementById('availableStudentsList');
        studentListContainer.innerHTML = '<p>Loading students...</p>';
        try {
            const response = await fetch(`../ajax/get_available_students.php?strand_id=${window.strandId}`);
            const students = await response.json();
            if (students.error) throw new Error(students.error);

            if (students.length === 0) {
                studentListContainer.innerHTML = '<p>No new students are available to add.</p>';
                return;
            }

            let html = students.map(s => `
            <div class="form-check student-item">
                <input class="form-check-input" type="checkbox" value="${s.id}" id="student-${s.id}">
                <label class="form-check-label" for="student-${s.id}">${s.fname} ${s.lname}</label>
            </div>`).join('');
            studentListContainer.innerHTML = html;
        } catch (error) {
            console.error('Failed to load students:', error);
            studentListContainer.innerHTML = '<p class="text-danger">Could not load student list.</p>';
        }
    }

    // SECTION 3: EVENT LISTENERS
    // --- Material Listeners ---
    if (materialType) materialType.addEventListener('change', injectMaterialInput);
    if (uploadModal) uploadModal.addEventListener('shown.bs.modal', injectMaterialInput);

    if (uploadForm) {
        uploadForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);

            // This fetch call sends the form data, including the hidden teacher_id
            fetch('../ajax/upload_material.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Hide the modal and reload the page to show the new material
                        bootstrap.Modal.getInstance(uploadModal).hide();
                        window.refreshMaterialList();
                    } else {
                        alert('Upload failed: ' + data.message);
                    }
                }).catch(err => {
                    console.error(err);
                    alert('An error occurred during upload.');
                });
        });
    }

    if (materialType) {
        materialType.addEventListener('change', injectMaterialInput);
    }
    if (uploadModal) {
        uploadModal.addEventListener('shown.bs.modal', injectMaterialInput);
    }
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const label = button.getAttribute('data-label');
            const type = button.getAttribute('data-type');
            const filePath = button.getAttribute('data-file');
            const linkUrl = button.getAttribute('data-link');
            editModal.querySelector('#edit-id').value = id;
            editModal.querySelector('#edit-label').value = label;
            editModal.querySelector('#edit-type').value = type;
            const container = editModal.querySelector('#edit-materialInputContainer');
            container.innerHTML = '';
            if (type === 'link') {
                container.innerHTML = `<label for="edit-link" class="form-label">Material Link</label><input type="url" class="form-control" id="edit-link" name="link" value="${linkUrl || ''}" required>`;
            } else {
                container.innerHTML = `<label class="form-label">Replace File</label><input type="file" class="form-control" name="file">${filePath ? `<small class="text-muted">Current: <a href="${filePath}" target="_blank">View</a></small>` : ''}`;
            }
        });
    }

    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(this);
            fetch('../ajax/update_material.php', { method: 'POST', body: fd })
                .then(res => res.json()).then(data => {
                    if (data.status === 'success') {
                        bootstrap.Modal.getInstance(editModal).hide();
                        refreshMaterialList();
                    } else { alert(data.message); }
                }).catch(err => alert("Update failed. Please try again."));
        });
    }

    // --- Assessment Listener ---
    const createAssessmentForm = document.getElementById('createAssessmentForm');
    if (createAssessmentForm) {
        createAssessmentForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Get values from all the fields in the modal
            const title = document.getElementById('assessmentTitle').value;
            const description = document.getElementById('assessmentDesc').value;
            const duration = document.getElementById('assessmentDuration').value;
            const attempts = document.getElementById('assessmentAttempts').value;

            try {
                const response = await fetch('../ajax/save-assessment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        strand_id: window.strandId,
                        title: title,
                        description: description,
                        duration: duration,
                        attempts: attempts
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Hide the modal and refresh the list of assessments
                    bootstrap.Modal.getInstance(document.getElementById('assessmentModal')).hide();
                    createAssessmentForm.reset();
                    refreshAssessmentList();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Failed to create assessment:', error);
                alert('An error occurred while creating the assessment.');
            }
        });
    }

    // Listener for submitting the EDIT Assessment Form
    const editAssessmentForm = document.getElementById('editAssessmentForm');
    if (editAssessmentForm) {
        editAssessmentForm.addEventListener('submit', async (e) => {
            e.preventDefault(); // This is the line that prevents the page from reloading

            const id = document.getElementById('editAssessmentId').value;
            const title = document.getElementById('editAssessmentTitle').value;
            const description = document.getElementById('editAssessmentDesc').value;
            const duration = document.getElementById('editAssessmentDuration').value;
            const attempts = document.getElementById('editAssessmentAttempts').value;

            try {
                const response = await fetch('../ajax/update_assessment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: id,
                        title: title,
                        description: description,
                        duration: duration,
                        attempts: attempts
                    })
                });
                const result = await response.json();
                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('editAssessmentModal')).hide();
                    refreshAssessmentList();
                } else {
                    alert('Error updating assessment: ' + result.message);
                }
            } catch (error) {
                console.error('Error updating assessment:', error);
                alert('An error occurred while updating the assessment.');
            }
        });
    }

    // This single listener handles all clicks inside the assessment list
    const assessmentList = document.getElementById('assessmentList');
    if (assessmentList) {
        let assessmentIdToDelete = null;

        // MAIN LISTENER: Handles all button clicks on the assessment cards
        assessmentList.addEventListener('click', async (e) => {
            const button = e.target.closest('button');
            if (!button) return;

            // --- NEW: Handle START QUIZ button for students ---
            if (button.classList.contains('start-quiz-btn')) {
                const assessmentId = button.dataset.assessmentId;

                button.disabled = true;
                button.textContent = 'Starting...';

                try {
                    const response = await fetch('../ajax/start_attempt.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ assessment_id: assessmentId })
                    });
                    const result = await response.json();

                    if (result.success) {
                        // If successful, redirect to the quiz page
                        window.location.href = `../quiz/take_quiz.php?assessment_id=${assessmentId}`;
                    } else {
                        // If there's an error (e.g., no attempts left), show it
                        alert('Error: ' + result.message);
                        button.disabled = false;
                        button.textContent = 'Take Quiz';
                    }
                } catch (error) {
                    console.error('Failed to start quiz:', error);
                    alert('An error occurred. Please try again.');
                    button.disabled = false;
                    button.textContent = 'Take Quiz';
                }
            }

            // 1. Handle VIEW ATTEMPTS button
            else if (button.classList.contains('view-attempts-btn')) {
                const assessmentId = button.dataset.id;
                const container = document.getElementById('attemptsListContainer');

                try {
                    const response = await fetch(`../ajax/get_attempts.php?assessment_id=${assessmentId}`);
                    const html = await response.text();
                    container.innerHTML = html;
                } catch (error) {
                    console.error('Failed to fetch attempts:', error);
                    container.innerHTML = '<div class="alert alert-danger">Could not load scores.</div>';
                }
            }

            // 2. Handle OPEN/CLOSE button
            else if (button.classList.contains('toggle-status-btn')) {
                const id = button.dataset.id;
                const status = button.dataset.status;
                try {
                    const response = await fetch('../ajax/toggle_assessment_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id, status: status })
                    });
                    const result = await response.json();
                    if (result.success) {
                        refreshAssessmentList();
                    }
                } catch (error) {
                    console.error('Error toggling status:', error);
                }
            }

            // 3. Handle EDIT button
            else if (button.classList.contains('edit-assessment-btn')) {
                document.getElementById('editAssessmentId').value = button.dataset.id;
                document.getElementById('editAssessmentTitle').value = button.dataset.title;
                document.getElementById('editAssessmentDesc').value = button.dataset.description;
                document.getElementById('editAssessmentDuration').value = button.dataset.duration;
                document.getElementById('editAssessmentAttempts').value = button.dataset.maxAttempts;
            }

            // 4. Handle OPEN DELETE MODAL button
            else if (button.dataset.bsTarget === '#deleteAssessmentModal') {
                assessmentIdToDelete = button.dataset.id;
            }
        });

        // SEPARATE LISTENER: Handles the final "Delete" click inside the modal
        const confirmDeleteBtn = document.getElementById('confirmDeleteAssessmentBtn');
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', async () => {
                if (!assessmentIdToDelete) return;

                try {
                    const response = await fetch('../ajax/delete_assessment.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: assessmentIdToDelete })
                    });
                    const result = await response.json();

                    if (result.success) {
                        bootstrap.Modal.getInstance(document.getElementById('deleteAssessmentModal')).hide();
                        refreshAssessmentList();
                    } else {
                        alert('Error: ' + result.message);
                    }
                } catch (error) {
                    console.error('Error deleting assessment:', error);
                }
            });
        }
    }

    // --- REVIEW ATTEMPT MODAL LOGIC ---
    // --- NEW AND IMPROVED REVIEW ATTEMPT MODAL LOGIC ---
    // This listens for clicks inside the container that holds the student list
    const attemptsModalBody = document.getElementById('attemptsListContainer');
    if (attemptsModalBody) {

        attemptsModalBody.addEventListener('click', async (event) => {
            const reviewButton = event.target.closest('.review-attempt-btn');

            // If the click was not on a review button, do nothing
            if (!reviewButton) return;

            event.preventDefault(); // Stop the link from doing anything else

            const attemptId = reviewButton.dataset.attemptId;
            const modalElement = document.getElementById('reviewAttemptModal');
            const modalBody = document.getElementById('reviewAttemptBody');
            const reviewModal = new bootstrap.Modal(modalElement);

            // 1. Show a loading message and MANUALLY open the review modal
            modalBody.innerHTML = '<p class="text-center">Loading review...</p>';
            reviewModal.show();

            // 2. Fetch the review details
            try {
                const response = await fetch(`../ajax/get_attempt_details.php?attempt_id=${attemptId}`);
                if (!response.ok) {
                    throw new Error('Failed to fetch attempt details.');
                }
                const html = await response.text();
                modalBody.innerHTML = html;
            } catch (error) {
                console.error('Error loading attempt review:', error);
                modalBody.innerHTML = '<div class="alert alert-danger">Could not load the attempt review.</div>';
            }
        });
    }

    // Add this inside your DOMContentLoaded listener

    // --- LOGIC FOR CUSTOM CLOSE BUTTON ON REVIEW MODAL ---
    const reviewModalElement = document.getElementById('reviewAttemptModal');
    if (reviewModalElement) {
        const closeReviewBtn = document.getElementById('closeReviewModalBtn');

        closeReviewBtn.addEventListener('click', () => {
            const reviewModalInstance = bootstrap.Modal.getInstance(reviewModalElement);
            if (reviewModalInstance) {
                reviewModalInstance.hide();
            }
        });
    }

    // --- QUESTIONS modal — populate hidden inputs before open ---
    if (questionsModal) {
        questionsModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;

            const assessmentId = button.getAttribute('data-assessment-id');

            console.log("The assessment ID from the clicked button is:", assessmentId);

            const assessmentInput = document.getElementById('assessmentIdInput');
            const strandInput = document.getElementById('questionStrandId');

            if (assessmentInput) {
                assessmentInput.value = assessmentId;
            }
            if (strandInput) {
                strandInput.value = window.strandId;
            }
        });
    }

    // --- Save Questions Listener ---
    if (questionForm) {
        questionForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const assessmentId = this.querySelector('#assessmentIdInput').value;
            const questionBlocks = this.querySelectorAll('.question-block');
            const questionsData = [];

            questionBlocks.forEach(block => {
                const questionText = block.querySelector('.question-text').value;
                const questionType = block.querySelector('.question-type').value;

                let options = [];
                if (questionType === 'mcq') {
                    block.querySelectorAll('input[name="options[]"]').forEach(opt => {
                        options.push(opt.value);
                    });
                }

                const correctAnswerSelect = block.querySelector('select[name="correct_answer"]');
                const correctAnswerInput = block.querySelector('input[name="correct_answer"]');
                const correctAnswer = correctAnswerSelect ? correctAnswerSelect.value : (correctAnswerInput ? correctAnswerInput.value : null);

                // Basic validation to ensure question text is not empty
                if (questionText.trim() !== '') {
                    questionsData.push({
                        text: questionText,
                        type: questionType,
                        options: options,
                        answer: correctAnswer
                    });
                }
            });

            // Prepare data for sending
            const dataToSend = {
                strand_id: window.strandId,
                assessment_id: assessmentId,
                questions: questionsData
            };

            try {
                const response = await fetch('../ajax/save_questions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(dataToSend)
                });

                const result = await response.json();

                if (result.status === 'success') {
                    alert(result.message);
                    bootstrap.Modal.getInstance(questionsModal).hide();
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                console.error('Failed to save questions:', error);
                alert('Error: ' + error.message);
            }
        });
    }

    // --- Populates the Question Modal with the correct Assessment ID ---
    if (questionsModal) {
        questionsModal.addEventListener('show.bs.modal', function (event) {
            // Get the button that triggered the modal
            const button = event.relatedTarget;
            if (!button) return;

            // Extract the assessment ID from the button's data-assessment-id attribute
            const assessmentId = button.getAttribute('data-assessment-id');

            // Find the hidden input field inside the modal's form
            const assessmentInput = document.getElementById('assessmentIdInput');

            // Set the value of the hidden input
            if (assessmentInput) {
                assessmentInput.value = assessmentId;
            }
        });
    }

    // --- MANAGE QUESTIONS MODAL LOGIC ---
    if (!questionsModal) return;

    // This event fires right when the "Manage Questions" modal is opened
    questionsModal.addEventListener('show.bs.modal', async (event) => {
        const button = event.relatedTarget; // The "Manage Questions" button
        const assessmentId = button.dataset.assessmentId;

        // Set the hidden assessment_id input in the form
        document.getElementById('assessmentIdInput').value = assessmentId;

        const formBuilder = document.getElementById('formBuilder');
        formBuilder.innerHTML = '<p class="text-center">Loading questions...</p>'; // Show a loading message

        try {
            // Use the new PHP file to fetch existing questions
            const response = await fetch(`../ajax/get_questions.php?assessment_id=${assessmentId}`);
            const questions = await response.json();

            formBuilder.innerHTML = ''; // Clear the loading message

            if (questions.length > 0) {
                // If questions exist, add them to the form
                questions.forEach(q => {
                    addQuestionBlock(formBuilder, q); // A helper function we will use
                });
            } else {
                // If no questions exist, show a message
                formBuilder.innerHTML = '<p class="text-center text-muted">No questions have been added yet.</p>';
            }
        } catch (error) {
            console.error('Failed to load questions:', error);
            formBuilder.innerHTML = '<p class="text-center text-danger">Could not load questions.</p>';
        }
    });

    // Helper function to add a question block to the form
    function addQuestionBlock(container, data = null) {
        const template = document.getElementById('questionTemplate');
        const clone = template.content.cloneNode(true);
        const questionBlock = clone.querySelector('.question-block');

        if (data) {
            // If we have existing data, pre-fill the fields
            questionBlock.querySelector('.question-text').value = data.question_text || '';
            questionBlock.querySelector('.question-type').value = data.question_type || '';

            // This part would be expanded to handle options and correct answers
            // For now, it just displays the main question text and type
        }

        // Clear the "No questions" message if it exists
        const noQuestionsMessage = container.querySelector('p.text-center');
        if (noQuestionsMessage) {
            noQuestionsMessage.remove();
        }

        container.appendChild(clone);
        updateQuestionNumbers(); // A function to keep question numbers correct
    }

    // You might already have this function, it ensures question numbers are always correct
    function updateQuestionNumbers() {
        const allQuestions = document.querySelectorAll('#formBuilder .question-block');
        allQuestions.forEach((q, index) => {
            q.querySelector('.q-index').textContent = index + 1;
        });
    }

    // --- Universal Media Modal Listener ---
    if (mediaModal) {
        // This event fires just before the modal is shown
        mediaModal.addEventListener('show.bs.modal', function (event) {
            // Get the icon link that was clicked to open the modal
            const button = event.relatedTarget;

            // Extract the data we stored in the link's data-* attributes
            const type = button.getAttribute('data-type');
            const url = button.getAttribute('data-url');
            const label = button.getAttribute('data-label');

            // Get the elements inside the modal that we need to update
            const modalTitle = document.getElementById('mediaModalLabel');
            const modalBody = document.getElementById('mediaModalBody');
            const downloadLink = document.getElementById('mediaDownloadLink');

            // 1. Update the modal's title
            modalTitle.textContent = label;

            let contentHTML = '';

            // 2. Build the correct HTML content based on the media type
            switch (type) {
                case 'image':
                    contentHTML = `<img src="${url}" class="img-fluid w-100" alt="${label}">`;
                    downloadLink.style.display = 'inline-block';
                    break;
                case 'video':
                    contentHTML = `<video controls autoplay class="w-100" style="max-height: 70vh;"><source src="${url}" type="video/mp4">Your browser does not support the video tag.</video>`;
                    downloadLink.style.display = 'inline-block';
                    break;
                case 'audio':
                    contentHTML = `<audio controls autoplay class="w-100"><source src="${url}" type="audio/mpeg">Your browser does not support the audio element.</audio>`;
                    downloadLink.style.display = 'inline-block';
                    break;
                case 'file': // For PDFs
                    contentHTML = `<iframe src="${url}" style="width:100%; height:75vh; border:0;"></iframe>`;
                    downloadLink.style.display = 'inline-block';
                    break;
                default:
                    contentHTML = `<p class="text-center">Cannot preview this file type.</p>`;
                    downloadLink.style.display = 'none'; // Hide download for unknown types
            }

            // 3. Inject the new content and update the download link
            modalBody.innerHTML = contentHTML;
            downloadLink.href = url;
        });

        // --- Logic for Deleting a Learning Material ---
        const deleteMaterialModal = document.getElementById('deleteMaterialModal');
        if (deleteMaterialModal) {
            let materialIdToDelete = null;
            let materialCardToDelete = null;

            // 1. When the modal is about to be shown, get the material ID
            deleteMaterialModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                materialIdToDelete = button.getAttribute('data-bs-id');
                materialCardToDelete = button.closest('.card.mb-3');
            });

            // 2. When the final "Delete" button inside the modal is clicked
            const confirmBtn = document.getElementById('confirmDeleteMaterialBtn');
            confirmBtn.addEventListener('click', function () {
                if (materialIdToDelete) {

                    const formData = new FormData();
                    formData.append('material_id', materialIdToDelete);

                    // This path goes up TWO levels to the root ajax folder
                    fetch('../ajax/delete_material.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // If successful, remove the card from the page instantly
                                if (materialCardToDelete) {
                                    materialCardToDelete.remove();
                                }
                                // Hide the modal
                                const modalInstance = bootstrap.Modal.getInstance(deleteMaterialModal);
                                modalInstance.hide();
                            } else {
                                alert('Error: ' + (data.message || 'Could not delete the material.'));
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred. Please try again.');
                        });
                }
            });
        }

        // --- Participant Listeners ---
        if (participantModal) {
            participantModal.addEventListener('show.bs.modal', loadAvailableStudents);
        }

        if (studentSearchInput) {
            studentSearchInput.addEventListener('keyup', () => {
                const filter = studentSearchInput.value.toLowerCase();
                document.querySelectorAll('.student-item').forEach(item => {
                    const label = item.querySelector('label').textContent.toLowerCase();
                    item.style.display = label.includes(filter) ? '' : 'none';
                });
            });
        }

        if (addSelectedStudentsBtn) {
            addSelectedStudentsBtn.addEventListener('click', async () => {
                const selectedIds = Array.from(document.querySelectorAll('#availableStudentsList .form-check-input:checked')).map(cb => cb.value);
                if (selectedIds.length === 0) {
                    alert('Please select at least one student.');
                    return;
                }
                try {
                    // This path goes up TWO levels from teacher/strand/ to the root ajax folder
                    const response = await fetch('../ajax/add_participant.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ strand_id: strandId, student_ids: selectedIds })
                    });
                    const result = await response.json();
                    if (result.status === 'success') {
                        bootstrap.Modal.getInstance(participantModal).hide();
                        refreshParticipantList();
                    } else {
                        throw new Error(result.message);
                    }
                } catch (error) {
                    console.error('Failed to add participants:', error);
                    alert('An error occurred while adding participants.');
                }
            });
        }

        if (participantListContainer) {
            participantListContainer.addEventListener('click', async (event) => {
                const button = event.target.closest('.remove-participant-btn');
                if (button) {
                    const participantId = button.dataset.participantId;
                    if (confirm('Are you sure you want to remove this participant?')) {
                        try {
                            // This path goes up TWO levels from teacher/strand/ to the root ajax folder
                            const response = await fetch('../ajax/remove_participant.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ strand_id: strandId, participant_id: participantId })
                            });
                            const result = await response.json();
                            if (result.success) {
                                refreshParticipantList();
                            } else {
                                throw new Error(result.message);
                            }
                        } catch (error) {
                            console.error('Failed to remove participant:', error);
                            alert('An error occurred while removing the participant: ' + error.message);
                        }
                    }
                }
            });
        }

        // 4. IMPORTANT: This event fires when the modal is closed
        mediaModal.addEventListener('hide.bs.modal', function () {
            const modalBody = document.getElementById('mediaModalBody');
            // This clears the content, stopping any video or audio from playing in the background
            modalBody.innerHTML = '';
        });
    }

    // ======================================================
    // SECTION 4: INITIAL PAGE LOAD & TAB CONTROLS
    // ======================================================
    const assessmentsTabBtn = document.querySelector('a[href="#assessments"]');
    if (assessmentsTabBtn) {
        refreshAssessmentList();
        assessmentsTabBtn.addEventListener('shown.bs.tab', refreshAssessmentList);
    }

    const participantsTabBtn = document.querySelector('a[href="#participants"]');
    if (participantsTabBtn) {
        participantsTabBtn.addEventListener('shown.bs.tab', refreshParticipantList);
        if (document.querySelector('#participants').classList.contains('active')) {
            refreshParticipantList();
        }
    }

});