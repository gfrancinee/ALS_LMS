document.addEventListener('DOMContentLoaded', () => {



    // Add this to initialize the editor in the Edit modal
    tinymce.init({
        selector: '#editAssessmentDesc',
        plugins: 'lists link image table code help wordcount',
        toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright | bullist numlist outdent indent | help'
    });

    // --- CORRECTED CODE TO ACTIVATE TAB FROM URL HASH ---
    const urlHash = window.location.hash; // e.g., "#assessments"
    if (urlHash) {
        const tabToActivate = document.querySelector('.nav-tabs a[href="' + urlHash + '"]');

        if (tabToActivate) {
            const tab = new bootstrap.Tab(tabToActivate);
            tab.show();

            // ADD THIS LINE: After showing the tab, remove the hash from the URL
            // This prevents the tab from being "sticky" on future reloads.
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }
    }
    // Modals
    const editModal = document.getElementById('editMaterialModal');
    const assessmentModal = document.getElementById('assessmentModal');
    const participantModal = document.getElementById('participantModal');
    const mediaModal = document.getElementById('mediaModal');

    // Forms, Containers & AlertsA
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


    // --- AJAX Refresh Functions ---
    window.refreshMaterialList = function () {
        if (!strandId) return;
        const materialListContainer = document.getElementById('materialListContainer'); // Ensure this ID exists in your HTML
        if (!materialListContainer) return;

        // Fetch the updated list of materials from a new PHP script
        fetch(`../ajax/get_materials.php?strand_id=${strandId}`)
            .then(response => response.text())
            .then(html => {
                // Replace the contents of the container with the new list
                materialListContainer.innerHTML = html;
            })
            .catch(err => {
                console.error('Failed to refresh materials:', err);
                materialListContainer.innerHTML = '<div class="alert alert-danger">Could not load materials.</div>';
            });
    };

    // --- Question Builder (Final Corrected Version) ---
    const questionsModal = document.getElementById('questionsModal');
    if (questionsModal) {
        // Finds the elements INSIDE that specific modal
        const formBuilder = questionsModal.querySelector('#formBuilder');
        const addQuestionBtn = questionsModal.querySelector('#addQuestionBtn');
        const questionTemplate = document.getElementById('questionTemplate'); // Corrected case

        // Check if all parts exist
        if (formBuilder && addQuestionBtn && questionTemplate) {

            function refreshIndices() {
                const allQuestionBlocks = formBuilder.querySelectorAll('.question-block');
                allQuestionBlocks.forEach((questionBlock, index) => {
                    const qIndexElement = questionBlock.querySelector('.q-index');
                    if (qIndexElement) {
                        qIndexElement.textContent = index + 1;
                    }
                });
            }

            function addQuestion() {
                const clone = questionTemplate.content.cloneNode(true);
                const newBlock = clone.querySelector('.question-block');

                // --- START: Full functionality for each new question ---
                // Add remove button listener
                newBlock.querySelector('.remove-question').addEventListener('click', () => {
                    newBlock.remove();
                    refreshIndices();
                });

                // Add move up/down listeners
                newBlock.querySelector('.move-up').addEventListener('click', () => {
                    if (newBlock.previousElementSibling) {
                        formBuilder.insertBefore(newBlock, newBlock.previousElementSibling);
                        refreshIndices();
                    }
                });

                newBlock.querySelector('.move-down').addEventListener('click', () => {
                    if (newBlock.nextElementSibling) {
                        formBuilder.insertBefore(newBlock.nextElementSibling, newBlock);
                        refreshIndices();
                    }
                });

                // Add listener for question type change
                const typeSelect = newBlock.querySelector('.question-type');
                typeSelect.addEventListener('change', () => {
                    buildAnswerArea(newBlock, typeSelect.value);
                });
                // --- END: Full functionality ---

                formBuilder.appendChild(newBlock);
                refreshIndices(); // Re-number after adding
            }

            // Function to build the answer area (MCQ, True/False, etc.)
            function buildAnswerArea(block, type) {
                const area = block.querySelector(".answer-area");
                area.innerHTML = ""; // Clear previous options
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
                    area.innerHTML = `<label class="form-label">Expected Answer</label><input type="text" class="form-control" name="correct_answer" placeholder="Enter correct answer (Optional)">`;
                }
            }

            // --- Event Listeners ---
            addQuestionBtn.addEventListener('click', addQuestion);

            questionsModal.addEventListener('shown.bs.modal', function () {
                refreshIndices();
            });

        }
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
            const adminLabel = participant.role === 'admin' ? '<span class="badge text-success ms-2">Teacher</span>' : '';

            // The remove button is ONLY created if the user is a teacher
            let removeButtonHtml = '';
            if (window.userRole === 'teacher' && participant.role !== 'admin') {
                removeButtonHtml = `<button class="btn text-danger btn-sm me-3 btn-pill-hover remove-participant-btn" data-participant-id="${participant.participant_id}"><i class="bi bi-trash3"></i> Remove</button>`;
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

    async function loadAvailableStudents(searchQuery = '') {
        const studentListContainer = document.getElementById('availableStudentsList');
        if (!studentListContainer) {
            console.error('availableStudentsList element not found');
            return;
        }

        studentListContainer.innerHTML = '<p class="text-center text-muted p-3">Loading students...</p>';

        try {
            // It now sends the strandId AND the search query
            const response = await fetch(`../ajax/get_available_students.php?strand_id=${strandId}&search=${encodeURIComponent(searchQuery)}`);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const students = await response.json();

            if (students.error) {
                studentListContainer.innerHTML = `<div class="alert alert-danger">${students.error}</div>`;
                return;
            }

            if (students.length === 0) {
                // Shows a different message if the search found nothing
                if (searchQuery.length > 0) {
                    studentListContainer.innerHTML = '<p class="text-muted text-center p-3">No students found matching that search.</p>';
                } else {
                    studentListContainer.innerHTML = '<p class="text-muted text-center p-3">No new students are available to add.</p>';
                }
                return;
            }

            // Your original (correct) code to build the list
            let html = students.map(s => `
            <div class="form-check student-item">
                <input class="form-check-input" type="checkbox" value="${s.id}" id="student-${s.id}">
                <label class="form-check-label" for="student-${s.id}">
                    ${s.lname}, ${s.fname} <span class="text-muted">(${s.grade_level})</span>
                </label>
            </div>
            `).join('');

            studentListContainer.innerHTML = html;

        } catch (error) {
            console.error('Failed to load students:', error);
            studentListContainer.innerHTML = '<div class="alert alert-danger">Could not load student list. Please try again.</div>';
        }
    }

    // SECTION 3: EVENT LISTENERS

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

    // --- Assessment Listener ---
    const createAssessmentContainer = document.getElementById('createAssessmentContainer');
    if (createAssessmentContainer) {
        const collapseInstance = new bootstrap.Collapse(createAssessmentContainer, {
            toggle: false // We will control it manually
        });

        // Listen for clicks on ANY "Create Assessment" button
        document.querySelectorAll('.create-assessment-btn').forEach(button => {
            button.addEventListener('click', function (event) {
                // Get the category ID from the button that was clicked
                const categoryId = this.getAttribute('data-category-id');
                const categoryInput = createAssessmentContainer.querySelector('#assessmentCategoryId');
                if (categoryId) {
                    categoryInput.value = categoryId;
                }

                // Show the form
                createAssessmentContainer.classList.remove('d-none');
                collapseInstance.show();
            });
        });

        // Add a listener to scroll down AFTER the form has opened
        createAssessmentContainer.addEventListener('shown.bs.collapse', function () {
            this.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        // Find the Cancel button inside the form
        const cancelBtn = createAssessmentContainer.querySelector('[data-bs-target="#createAssessmentContainer"]');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                // When the hide animation is done, make it fully disappear
                createAssessmentContainer.addEventListener('hidden.bs.collapse', function () {
                    createAssessmentContainer.classList.add('d-none');
                }, { once: true }); // This listener only runs once
            });
        }
    }

    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: '#assessmentDesc',
            plugins: 'lists link image media table code help wordcount',
            toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright | bullist numlist outdent indent | link image media | code help',
            menubar: false,
            height: 250
        });
    } else {
        console.error("TinyMCE script not loaded.");
    }

    // This script controls the form logic
    document.addEventListener('DOMContentLoaded', () => {
        // Get all the elements for the CREATE form
        const durationContainer = document.getElementById('durationContainer');
        const attemptsContainer = document.getElementById('attemptsContainer');
        const totalPointsContainer = document.getElementById('totalPointsContainer'); // New field

        const durationInput = document.getElementById('assessmentDuration');
        const attemptsInput = document.getElementById('assessmentAttempts');
        const totalPointsInput = document.getElementById('assessmentTotalPoints'); // New field

        const allRadios = document.querySelectorAll('.assessment-type-option');

        function toggleCreateAssessmentFields() {
            const selectedTypeInput = document.querySelector('#createAssessmentForm input[name="type"]:checked');
            if (!selectedTypeInput) return;

            const selectedType = selectedTypeInput.value;

            // Check if it's a quiz or exam
            if (selectedType === 'quiz' || selectedType === 'exam') {
                // Show Quiz fields
                durationContainer.style.display = 'block';
                attemptsContainer.style.display = 'block';
                durationInput.required = true;
                durationInput.disabled = false;
                attemptsInput.required = true;
                attemptsInput.disabled = false;

                // Hide Activity fields
                totalPointsContainer.style.display = 'none';
                totalPointsInput.required = false;
                totalPointsInput.disabled = true;

            } else { // This is for 'activity', 'assignment', or 'project'
                // Hide Quiz fields
                durationContainer.style.display = 'none';
                attemptsContainer.style.display = 'none';
                durationInput.required = false;
                durationInput.disabled = true;
                attemptsInput.required = false;
                attemptsInput.disabled = true;

                // Show Activity fields
                totalPointsContainer.style.display = 'block';
                totalPointsInput.required = true;
                totalPointsInput.disabled = false;
            }
        }

        // Add a 'change' event listener to every radio button
        allRadios.forEach(radio => {
            radio.addEventListener('change', toggleCreateAssessmentFields);
        });

        // Run it once on page load to set the default state (for Quiz)
        toggleCreateAssessmentFields();
    });

    // --- Handles the 'Create Assessment' form submission ---
    const createAssessmentForm = document.getElementById('createAssessmentForm');
    if (createAssessmentForm) {
        const createAssessmentContainer = document.getElementById('createAssessmentContainer');
        const collapseInstance = bootstrap.Collapse.getOrCreateInstance(createAssessmentContainer);

        createAssessmentForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            tinymce.triggerSave();

            const formData = new FormData(createAssessmentForm);
            formData.append('strand_id', strandId);

            try {
                const response = await fetch('../ajax/create_assessment.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    const assessment = result.assessment;
                    const categoryId = assessment.category_id;
                    const typeCapitalized = assessment.type.charAt(0).toUpperCase() + assessment.type.slice(1);
                    const hasDescription = assessment.description && assessment.description.trim().replace(/<p>&nbsp;<\/p>/g, '').length > 0;

                    // --- UPDATE: Check if the type is 'quiz' or 'exam' ---
                    const isTimedAssessment = (assessment.type === 'quiz' || assessment.type === 'exam');

                    const newItemHTML = `
<li>
    <div class="assessment-item">
        <div class="d-flex justify-content-between align-items-start">
            <div class="flex-grow-1">
                <a href="/ALS_LMS/strand/preview_assessment.php?id=${assessment.id}" class="assessment-item-link">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fw-bold">${assessment.title}</span>
                            <span class="badge bg-light text-dark fw-normal ms-2">${typeCapitalized}</span>
                        </div>
                        
                        ${isTimedAssessment ? `
                        <div class="text-muted small">
                            <span class="me-3"><i class="bi bi-clock"></i> ${assessment.duration_minutes} mins</span>
                            <span><i class="bi bi-arrow-repeat"></i> ${assessment.max_attempts} attempt(s)</span>
                        </div>
                        ` : ''}
                        
                    </div>
                </a>
                ${hasDescription ? `
                <div class="mt-2">
                    <button class="btn btn-sm py-0 btn-toggle-desc" type="button" data-bs-toggle="collapse" data-bs-target="#desc-${assessment.id}">
                        Show/Hide Description
                    </button>
                </div>` : ''}
            </div>
            <div class="d-flex align-items-center gap-2 ps-3">
                <div class="form-check form-switch">
                    <input class="form-check-input assessment-status-toggle" type="checkbox" role="switch" data-id="${assessment.id}">
                    <label class="form-check-label small">Closed</label>
                </div>
                <div class="dropdown">
                    <button class="btn btn-options" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        
                        ${isTimedAssessment ? `
                        <li><a class="dropdown-item text-center" href="/ALS_LMS/strand/manage_assessment.php?id=${assessment.id}"><i class="bi bi-list-check me-2"></i> Manage Questions</a></li>
                        ` : ''}

                        <li><a class="dropdown-item text-center" href="view_submissions.php?assessment_id=${assessment.id}"><i class="bi bi-person-check-fill me-2"></i> View Submissions</a></li>
                        
                        <li><hr class="dropdown-divider"></li>
                        <li><button class="dropdown-item text-success edit-assessment-btn" type="button" data-bs-toggle="modal" data-bs-target="#editAssessmentModal" data-id="${assessment.id}"><i class="bi bi-pencil-square me-2"></i> Edit</button></li>
                        <li><button class="dropdown-item text-danger delete-assessment-btn" type="button" data-bs-toggle="modal" data-bs-target="#deleteAssessmentModal" data-id="${assessment.id}" data-title="${assessment.title}"><i class="bi bi-trash3 me-2"></i> Delete</button></li>
                    </ul>
                </div>
            </div>
        </div>
        ${hasDescription ? `
        <div class="collapse" id="desc-${assessment.id}">
            <div class="small text-muted mt-2 p-3 bg-light rounded">
                ${assessment.description} 
            </div>
        </div>` : ''}
    </div>
</li>`;

                    const listContainer = document.querySelector(`#collapse-cat-${categoryId} .list-unstyled`);
                    if (listContainer) {
                        const emptyMsg = listContainer.querySelector('.fst-italic');
                        if (emptyMsg) emptyMsg.remove();
                        listContainer.insertAdjacentHTML('beforeend', newItemHTML);
                    }

                    collapseInstance.hide();
                    createAssessmentForm.reset();
                    tinymce.get('assessmentDesc').setContent('');

                } else {
                    alert('Error: ' + (result.error || 'Could not create assessment.'));
                }
            } catch (error) {
                console.error('Submission failed:', error);
                alert('An error occurred. Please try again.');
            }
        });
    }

    // --- Handles the Open/Close toggle switch for assessments ---
    document.body.addEventListener('change', async function (event) {
        if (event.target.classList.contains('assessment-status-toggle')) {
            const toggleSwitch = event.target;
            const assessmentId = toggleSwitch.dataset.id;
            const isChecked = toggleSwitch.checked; // This is the new state
            const label = toggleSwitch.nextElementSibling;
            const oldLabelText = label.textContent; // Store the old label in case of error

            // Optimistic UI update for good UX
            label.textContent = isChecked ? 'Open' : 'Closed';

            try {
                // --- UPDATED FETCH ---
                const response = await fetch('../ajax/toggle_assessment_status.php', {
                    method: 'POST',
                    // 1. Set header to JSON
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    // 2. Send a JSON string with the ID *and* the new state
                    body: JSON.stringify({
                        assessment_id: assessmentId,
                        is_open: isChecked
                    })
                });

                if (!response.ok) {
                    // Handle server errors (like 500, 403)
                    throw new Error(`Server responded with status: ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    // 3. Use the confirmed label from the server.
                    // This handles the case where the server logic is different.
                    label.textContent = data.new_status_label;
                    // You could add a success toast here
                } else {
                    // Handle application errors (e.g., "assessment not found")
                    throw new Error(data.error || 'Failed to update status.');
                }

            } catch (error) {
                // 4. Revert on any error
                label.textContent = oldLabelText; // Revert to the original text
                toggleSwitch.checked = !toggleSwitch.checked; // Un-toggle the switch
                alert(`Error: ${error.message}`);
            }
        }
    });

    // Declare the strandId only ONCE at the top for all features to use.
    const strandId = new URLSearchParams(window.location.search).get('id');

    // --- START: YOUR ORIGINAL ASSESSMENT CATEGORY LOGIC (RESTORED & UNCHANGED) ---
    const categoriesModal = document.getElementById('manageCategoriesModal');
    if (categoriesModal) {
        const addCategoryForm = document.getElementById('add-category-form');
        const categoryList = document.getElementById('category-list');

        const loadCategories = async () => {
            if (!strandId) return;
            const response = await fetch(`../ajax/manage_categories.php?action=fetch&strand_id=${strandId}`);
            const categories = await response.json();
            categoryList.innerHTML = '';
            if (categories.success && categories.data.length > 0) {
                categories.data.forEach(cat => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item';
                    li.textContent = cat.name;
                    categoryList.appendChild(li);
                });
            } else {
                categoryList.innerHTML = '<li class="list-group-item text-muted">No categories created yet.</li>';
            }
        };

        categoriesModal.addEventListener('show.bs.modal', loadCategories);

        addCategoryForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(addCategoryForm);
            formData.append('action', 'create');
            formData.append('strand_id', strandId);
            const submitButton = addCategoryForm.querySelector('button[type="submit"]');
            submitButton.disabled = true;

            const response = await fetch('../ajax/manage_categories.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success && result.newCategory) {
                addCategoryForm.reset();
                await loadCategories();
                document.getElementById('no-categories-message')?.remove();
                const newCategory = result.newCategory;
                const accordionContainer = document.getElementById('assessmentAccordion');
                const newAccordionItemHTML = `
                    <div class="accordion-item" data-category-id="${newCategory.id}">
                        <h2 class="accordion-header">
                            <div class="d-flex align-items-center w-100">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-cat-${newCategory.id}"><i class="bi bi-folder me-2"></i> ${newCategory.name}</button>
                                <div class="dropdown mb-2">
                                    <button class="btn btn-options" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><button class="dropdown-item text-success" type="button" data-bs-toggle="modal" data-bs-target="#categoryActionModal" data-action="edit" data-id="${newCategory.id}" data-name="${newCategory.name}"><i class="bi bi-pencil-square me-2"></i> Edit</button></li>
                                        <li><button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#categoryActionModal" data-action="delete" data-id="${newCategory.id}" data-name="${newCategory.name}"><i class="bi bi-trash3 me-2"></i> Delete</button></li>
                                    </ul>
                                </div>
                            </div>
                        </h2>
                        <div id="collapse-cat-${newCategory.id}" class="accordion-collapse collapse" data-bs-parent="#assessmentAccordion">
                            <div class="accordion-body"><ul class="list-unstyled mb-0"><li class="text-muted fst-italic">No assessments in this category yet.</li></ul><hr class="my-3"><div class="text-center"><button class="btn btn-link text-success btn-sm me-3 btn-pill-hover text-decoration-none create-assessment-btn" data-bs-toggle="collapse" data-bs-target="#createAssessmentContainer" data-category-id="${newCategory.id}"><i class="bi bi-plus-circle"></i> Create Assessment</button></div></div>
                        </div>
                    </div>`;
                accordionContainer.insertAdjacentHTML('beforeend', newAccordionItemHTML);
                const newAccordionItem = accordionContainer.lastElementChild;
                const newDropdownToggle = newAccordionItem.querySelector('[data-bs-toggle="dropdown"]');
                if (newDropdownToggle) {
                    new bootstrap.Dropdown(newDropdownToggle);
                }
            } else {
                alert('Error: ' + (result.error || 'Could not add category.'));
            }
            submitButton.disabled = false;
            submitButton.textContent = 'Add Category';
        });
    }

    const categoryActionModalEl = document.getElementById('categoryActionModal');
    if (categoryActionModalEl) {
        categoryActionModalEl.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const action = button.dataset.action;
            const catId = button.dataset.id;
            const catName = button.dataset.name;
            this.dataset.categoryId = catId;
            const modalTitle = this.querySelector('.modal-title');
            const modalBody = this.querySelector('.modal-body');
            const modalFooter = this.querySelector('.modal-footer');
            if (action === 'edit') {
                modalTitle.textContent = 'Edit Category Name';
                modalBody.innerHTML = `<label for="editCategoryName" class="form-label">Category Name</label><input type="text" id="editCategoryName" class="form-control" value="${catName}">`;
                modalFooter.innerHTML = `<button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary rounded-pill px-3" id="saveCategoryBtn">Save Changes</button>`;
            } else if (action === 'delete') {
                this._elementToDelete = button.closest('.accordion-item');
                modalTitle.textContent = 'Confirm Deletion';
                modalBody.innerHTML = `<p>Are you sure you want to delete "<strong>${catName}</strong>"?</p><p class="text-danger small">This action cannot be undone.</p>`;
                modalFooter.innerHTML = `<button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger rounded-pill px-3" id="deleteCategoryBtn">Delete</button>`;
            }
        });

        categoryActionModalEl.addEventListener('shown.bs.modal', function () {
            const editInput = document.getElementById('editCategoryName');
            if (editInput) {
                editInput.focus();
                editInput.select();
            }
        });

        categoryActionModalEl.addEventListener('click', async function (event) {
            const target = event.target;
            const modalInstance = bootstrap.Modal.getInstance(this);
            if (target.id === 'saveCategoryBtn') {
                const catId = this.dataset.categoryId;
                const newName = document.getElementById('editCategoryName').value;
                if (newName && newName.trim() !== '') {
                    const formData = new FormData();
                    formData.append('action', 'update');
                    formData.append('category_id', catId);
                    formData.append('category_name', newName);
                    const response = await fetch('../ajax/manage_categories.php', { method: 'POST', body: formData });
                    const result = await response.json();
                    if (result.success) {
                        const accordionButton = document.querySelector(`button[data-bs-target="#collapse-cat-${catId}"]`);
                        if (accordionButton) accordionButton.innerHTML = `<i class="bi bi-folder me-2"></i> ${result.updatedName}`;
                        const editButton = document.querySelector(`.dropdown-item[data-action="edit"][data-id="${catId}"]`);
                        if (editButton) editButton.dataset.name = result.updatedName;
                        modalInstance.hide();
                    } else {
                        alert("Could not save changes.");
                    }
                }
            }
            if (target.id === 'deleteCategoryBtn') {
                const catId = this.dataset.categoryId;
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('category_id', catId);
                const response = await fetch('../ajax/manage_categories.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    modalInstance.hide();
                    if (this._elementToDelete) {
                        this._elementToDelete.remove();
                    }
                } else {
                    alert('Error: Could not delete category.');
                }
            }
        });
    }
    // --- END: ASSESSMENT CATEGORY LOGIC ---


    // --- START: YOUR ORIGINAL MATERIAL CATEGORY LOGIC (UNCHANGED) ---
    const manageMaterialModal = document.getElementById('manageMaterialCategoriesModal');
    if (manageMaterialModal) {
        const materialActionModalEl = document.getElementById('materialCategoryActionModal');
        const materialActionModal = new bootstrap.Modal(materialActionModalEl);
        const materialActionForm = document.getElementById('materialCategoryActionForm');
        const addMaterialCategoryForm = document.getElementById('add-material-category-form');
        const materialCategoryListEl = document.getElementById('material-category-list');

        const loadMaterialCategories = async () => {
            if (!strandId) return;
            const response = await fetch(`../ajax/manage_material_categories.php?action=fetch&strand_id=${strandId}`);
            const result = await response.json();
            materialCategoryListEl.innerHTML = '';
            if (result.success && result.data.length > 0) {
                result.data.forEach(cat => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item';
                    li.textContent = cat.name;
                    materialCategoryListEl.appendChild(li);
                });
            } else {
                materialCategoryListEl.innerHTML = '<li class="list-group-item text-muted">No categories created yet.</li>';
            }
        };

        manageMaterialModal.addEventListener('show.bs.modal', loadMaterialCategories);

        addMaterialCategoryForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const input = e.target.querySelector('input[name="name"]');
            const name = input.value;
            if (!name) return;
            const formData = new FormData();
            formData.append('action', 'create');
            formData.append('name', name);
            formData.append('strand_id', strandId);
            const response = await fetch('../ajax/manage_material_categories.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success && result.data) {
                addMaterialCategoryForm.reset();
                await loadMaterialCategories();
                document.getElementById('no-material-categories-message')?.remove();
                const newCategory = result.data;
                const accordionContainer = document.getElementById('materialsAccordion');
                const newAccordionItemHTML = `
                    <div class="accordion-item" id="material-category-item-${newCategory.id}">
                        <h2 class="accordion-header">
                            <div class="d-flex align-items-center w-100">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#material-collapse-cat-${newCategory.id}"><i class="bi bi-folder me-2"></i> ${newCategory.name}</button>
                                <div class="dropdown mb-2">
                                    <button class="btn btn-options" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-three-dots-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><button class="dropdown-item text-success" type="button" data-bs-toggle="modal" data-bs-target="#materialCategoryActionModal" data-action="edit" data-id="${newCategory.id}" data-name="${newCategory.name}"><i class="bi bi-pencil-square me-2"></i> Edit</button></li>
                                        <li><button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#materialCategoryActionModal" data-action="delete" data-id="${newCategory.id}" data-name="${newCategory.name}"><i class="bi bi-trash3 me-2"></i> Delete</button></li>
                                    </ul>
                                </div>
                            </div>
                        </h2>
                        <div id="material-collapse-cat-${newCategory.id}" class="accordion-collapse collapse" data-bs-parent="#materialsAccordion">
                            <div class="accordion-body"><ul class="list-unstyled mb-0 material-list-group"><li class="text-muted fst-italic p-3 no-materials-message">No materials in this category yet.</li></ul><hr class="my-3"><div class="text-center"><button class="btn btn-link text-success btn-sm me-3 btn-pill-hoverr text-decoration-none upload-material-btn" data-bs-toggle="collapse" data-bs-target="#uploadMaterialContainer" data-category-id="${newCategory.id}"><i class="bi-file-earmark-plus-fill"></i> Upload Material</button></div></div>
                        </div>
                    </div>`;
                accordionContainer.insertAdjacentHTML('beforeend', newAccordionItemHTML);
            } else {
                alert('Error: ' + (result.error || 'Could not add category.'));
            }
        });

        materialActionModalEl.addEventListener('show.bs.modal', (e) => {
            const button = e.relatedTarget;
            const action = button.dataset.action;
            const id = button.dataset.id || '';
            const name = button.dataset.name || '';
            const modalTitle = materialActionModalEl.querySelector('.modal-title');
            const submitBtn = document.getElementById('materialCategorySubmitBtn');
            const nameGroup = document.getElementById('materialCategoryNameGroup');
            const deleteConfirm = document.getElementById('materialCategoryDeleteConfirm');
            materialActionForm.reset();
            document.getElementById('materialCategoryActionInput').value = action;
            document.getElementById('materialCategoryIdInput').value = id;
            document.getElementById('materialCategoryNameInput').value = name;
            if (action === 'edit') {
                modalTitle.textContent = 'Edit Category Name';
                submitBtn.textContent = 'Save Changes';
                submitBtn.className = 'btn btn-primary rounded-pill px-3';
                nameGroup.style.display = 'block';
                deleteConfirm.style.display = 'none';
            } else if (action === 'delete') {
                modalTitle.textContent = 'Delete Category';
                submitBtn.textContent = 'Delete';
                submitBtn.className = 'btn btn-danger rounded-pill px-3';
                nameGroup.style.display = 'none';
                deleteConfirm.style.display = 'block';
                document.getElementById('deleteMaterialCategoryName').textContent = name;
            }
        });

        materialActionModalEl.addEventListener('shown.bs.modal', () => {
            const nameInput = document.getElementById('materialCategoryNameInput');
            if (nameInput && nameInput.offsetParent !== null) {
                nameInput.focus();
                nameInput.select();
            }
        });

        materialActionForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(materialActionForm);
            formData.append('strand_id', strandId);
            const response = await fetch('../ajax/manage_material_categories.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                const action = formData.get('action');
                if (action === 'edit') {
                    const categoryId = result.data.id;
                    const newName = result.data.name;
                    const button = document.querySelector(`#material-category-item-${categoryId} .accordion-button`);
                    if (button) button.innerHTML = `<i class="bi bi-folder me-2"></i> ${newName}`;
                } else if (action === 'delete') {
                    const categoryId = formData.get('id');
                    document.getElementById(`material-category-item-${categoryId}`)?.remove();
                }
                await loadMaterialCategories();
                materialActionModal.hide();
            } else {
                alert('Error: ' + (result.error || 'An unknown error occurred.'));
            }
        });
    }
    // --- END: MATERIAL CATEGORY LOGIC ---

    // --- NEW: Auto-scroll to "Upload Material" form ---

    // 1. Find all the "Upload Material" buttons
    const uploadMaterialButtons = document.querySelectorAll('.upload-material-btn');

    // 2. Find the form container that collapses
    const uploadMaterialContainer = document.getElementById('uploadMaterialContainer');

    if (uploadMaterialButtons.length > 0 && uploadMaterialContainer) {

        // 3. Listen for when the collapse is *finished opening*
        uploadMaterialContainer.addEventListener('shown.bs.collapse', () => {
            // 4. Once it's open, scroll it into view
            uploadMaterialContainer.scrollIntoView({
                behavior: 'smooth', // Makes it a nice, smooth scroll
                block: 'start'    // Aligns it to the nearest edge
            });
        });
    }
    // --- END OF NEW CODE ---


    // --- START: UPLOAD MATERIAL LOGIC ---
    const uploadFormContainer = document.getElementById('uploadMaterialContainer');
    if (uploadFormContainer) {
        const uploadForm = document.getElementById('uploadMaterialForm');
        const categoryIdInput = document.getElementById('uploadMaterialCategoryId');
        const typeRadios = document.querySelectorAll('input[name="material_type"]');
        const fileGroup = document.getElementById('fileUploadGroup');
        const linkGroup = document.getElementById('linkUploadGroup');
        const fileInput = document.getElementById('materialFile');
        const linkInput = document.getElementById('materialLink');

        document.getElementById('materialsAccordion').addEventListener('click', function (e) {
            if (e.target.classList.contains('upload-material-btn')) {
                const categoryId = e.target.dataset.categoryId;
                categoryIdInput.value = categoryId;
            }
        });

        typeRadios.forEach(radio => {
            radio.addEventListener('change', function () {
                if (this.value === 'link') {
                    fileGroup.style.display = 'none';
                    linkGroup.style.display = 'block';
                    fileInput.required = false;
                    linkInput.required = true;
                } else {
                    fileGroup.style.display = 'block';
                    linkGroup.style.display = 'none';
                    fileInput.required = true;
                    linkInput.required = false;
                    if (this.value === 'image') fileInput.setAttribute('accept', 'image/*');
                    else if (this.value === 'video') fileInput.setAttribute('accept', 'video/*');
                    else if (this.value === 'audio') fileInput.setAttribute('accept', 'audio/*');
                    else fileInput.removeAttribute('accept');
                }
            });
        });

        uploadForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);

            // This line ensures the strandId is always sent with the upload
            formData.append('strand_id', strandId);

            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading...';

            try {
                const response = await fetch('../ajax/upload_material.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success && result.data) {
                    const categoryId = formData.get('category_id');
                    const newMaterial = result.data;

                    // 1. Find the reliable container, which is always present for each category.
                    const listContainer = document.querySelector(`#material-list-container-cat-${categoryId}`);

                    if (listContainer) {
                        // 2. Find the "No materials..." message.
                        const noMaterialsMessage = listContainer.querySelector('.no-materials-message');

                        // 3. Find the <ul>. It might not exist in an empty category.
                        let materialList = listContainer.querySelector('.material-list-group');

                        // 4. THIS IS THE FIX: If the <ul> doesn't exist, create it.
                        if (!materialList) {
                            materialList = document.createElement('ul');
                            materialList.className = 'list-unstyled mb-0 material-list-group';
                            listContainer.innerHTML = ''; // Clear the container (removes the "No materials" message)
                            listContainer.appendChild(materialList);
                        } else if (noMaterialsMessage) {
                            // If the list already exists but contains the message, remove the message.
                            noMaterialsMessage.remove();
                        }

                        // 5. Now that the list is guaranteed to exist, build and add the new item.
                        let iconClass = 'bi-file-earmark-text';
                        if (newMaterial.type === 'file' && newMaterial.file_path) {
                            const ext = newMaterial.file_path.split('.').pop().toLowerCase();
                            if (ext === 'pdf') iconClass = 'bi-file-earmark-pdf-fill text-danger';
                            else if (['ppt', 'pptx'].includes(ext)) iconClass = 'bi-file-earmark-slides-fill text-warning';
                        } else if (newMaterial.type === 'link') {
                            iconClass = 'bi-link-45deg text-primary';
                        } else if (newMaterial.type === 'image') {
                            iconClass = 'bi-card-image text-success';
                        } else if (newMaterial.type === 'video') {
                            iconClass = 'bi-play-circle-fill text-info';
                        } else if (newMaterial.type === 'audio') {
                            iconClass = 'bi-volume-up-fill text-purple';
                        }

                        const materialLink = newMaterial.link_url ? newMaterial.link_url : `/ALS_LMS/strand/view_material.php?id=${newMaterial.id}`;

                        const newMaterialHTML = `
            <li class="list-group-item d-flex justify-content-between align-items-center material-item" id="material-item-${newMaterial.id}">
                <a href="${materialLink}" target="_blank" class="material-item-link">
                    <div class="d-flex align-items-center">
                        <i class="bi ${iconClass} fs-2 me-3"></i>
                        <div>
                            <span class="fw-bold">${newMaterial.label}</span>
                            <span class="badge bg-light text-dark fw-normal ms-2">${newMaterial.type.charAt(0).toUpperCase() + newMaterial.type.slice(1)}</span>
                        </div>
                    </div>
                </a>
                <div class="dropdown">
                    <button class="btn btn-options" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button class="dropdown-item edit-material-btn text-success" data-bs-toggle="modal" data-bs-target="#editMaterialModal" data-id="${newMaterial.id}"><i class="bi bi-pencil-square me-2"></i> Edit</button></li>
                        <li><button class="dropdown-item delete-material-btn text-danger" data-bs-toggle="modal" data-bs-target="#deleteMaterialModal" data-id="${newMaterial.id}"><i class="bi bi-trash3 me-2"></i> Delete</button></li>
                    </ul>
                </div>
            </li>`;

                        materialList.insertAdjacentHTML('beforeend', newMaterialHTML);
                    }

                    // Reset and hide the form
                    uploadForm.reset();
                    const uploadFormContainer = document.getElementById('uploadMaterialContainer');
                    if (uploadFormContainer) {
                        const collapseInstance = bootstrap.Collapse.getInstance(uploadFormContainer);
                        if (collapseInstance) {
                            collapseInstance.hide();
                        }
                    }

                } else {
                    alert('Error: ' + (result.error || 'An unknown error occurred.'));
                }
            } catch (error) {
                console.error("Upload failed:", error);
                alert("A critical error occurred during upload. Please check the console.");
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'Upload';
            }
        });
    }

    // --- Edit Material Logic ---
    const editMaterialModalEl = document.getElementById('editMaterialModal');
    if (editMaterialModalEl) {
        const editForm = document.getElementById('editMaterialForm');
        const editMaterialId = document.getElementById('editMaterialId');
        const editLabel = document.getElementById('editLabel');
        const editType = document.getElementById('editType');
        const editFileGroup = document.getElementById('editFileGroup');
        const editLinkGroup = document.getElementById('editLinkGroup');
        const currentFile = document.getElementById('currentFile');
        const editLink = document.getElementById('editLink');
        const editFile = document.getElementById('editFile');
        let materialElementToUpdate = null; // This will store the <li> or <a> item

        // Function to show/hide fields based on material type
        function toggleEditFields(type) {
            if (type === 'link') {
                editFileGroup.style.display = 'none';
                editLinkGroup.style.display = 'block';
                editLink.required = true;
                editFile.required = false;
            } else {
                editFileGroup.style.display = 'block';
                editLinkGroup.style.display = 'none';
                editLink.required = false;
                editFile.required = false;
            }
        }

        // This part loads the data into the modal
        editMaterialModalEl.addEventListener('show.bs.modal', async function (event) {
            const button = event.relatedTarget;
            const materialId = button.dataset.id;

            // --- FIX: Find the correct parent element to update ---
            // Your HTML from the video shows the button is in a dropdown
            materialElementToUpdate = button.closest('.material-item');

            // Clear old data
            editForm.reset();
            editLabel.value = 'Loading...';
            currentFile.textContent = '';

            try {
                const response = await fetch(`../ajax/get_material_details.php?id=${materialId}`);
                if (!response.ok) throw new Error('Network error.');

                const result = await response.json();
                if (result.success) {
                    const data = result.data;
                    editMaterialId.value = materialId;
                    editLabel.value = data.label;
                    editType.value = data.type;

                    if (data.type === 'link') {
                        editLink.value = data.link_url;
                        currentFile.textContent = 'N/A';
                    } else {
                        editLink.value = '';
                        currentFile.textContent = data.file_path ? data.file_path.split('/').pop() : 'No file';
                    }
                    toggleEditFields(data.type);
                } else {
                    throw new Error(result.error || 'Could not load details.');
                }
            } catch (error) {
                console.error('Error fetching material details:', error);
                alert('Error: ' + error.message);
                bootstrap.Modal.getInstance(editMaterialModalEl).hide();
            }
        });

        // When the type <select> changes, show/hide the fields
        editType.addEventListener('change', () => {
            toggleEditFields(editType.value);
        });

        // This part saves the data
        editForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const newLabel = formData.get('label'); // Get the new label text

            try {
                const response = await fetch('../ajax/update_material.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) throw new Error('Network response was not ok');

                const result = await response.json();

                if (result.success) {
                    // --- THIS IS THE FIX ---
                    // 1. Close the modal
                    bootstrap.Modal.getInstance(editMaterialModalEl).hide();

                    // 2. Live-update the title on the page instead of reloading
                    if (materialElementToUpdate) {
                        // Find the label text element (assuming it has .fw-bold or a specific class)
                        // Let's try to find the 'span' that holds the title.
                        // This selector might need to be adjusted to match your HTML
                        const labelElement = materialElementToUpdate.querySelector('.material-title'); // Or '.fw-bold'
                        if (labelElement) {
                            labelElement.textContent = newLabel;
                        }
                    }
                    // --- END OF FIX ---

                } else {
                    throw new Error(result.message || 'An unknown error occurred.');
                }

            } catch (error) {
                console.error('Error updating material:', error);
                alert('Error: ' + error.message);
            }
        });
    }

    // --- Delete Material Logic ---
    const deleteMaterialModalEl = document.getElementById('deleteMaterialModal');
    if (deleteMaterialModalEl) {
        let materialIdToDelete = null;
        let materialElementToDelete = null;

        deleteMaterialModalEl.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            materialIdToDelete = button.dataset.id;
            materialElementToDelete = button.closest('.material-item');
        });

        document.getElementById('confirmDeleteMaterialBtn').addEventListener('click', async function () {
            const formData = new FormData();
            formData.append('id', materialIdToDelete);
            const response = await fetch('../ajax/delete_material.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                bootstrap.Modal.getInstance(deleteMaterialModalEl).hide();
                // Remove from page without reload
                if (materialElementToDelete) {
                    materialElementToDelete.remove();
                }
            } else {
                alert('Error: ' + result.error);
            }
        });
    }

    // --- START: Replace your ENTIRE "Edit Assessment Modal" JavaScript with this ---

    // --- Get references to the form elements ---
    const editAssessmentModalEl = document.getElementById('editAssessmentModal');
    const editAssessmentForm = document.getElementById('editAssessmentForm');
    const editDurationContainer = document.getElementById('editDurationContainer');
    const editAttemptsContainer = document.getElementById('editAttemptsContainer');
    const editDurationInput = document.getElementById('editAssessmentDuration');
    const editAttemptsInput = document.getElementById('editAssessmentAttempts');
    const editTypeRadios = document.querySelectorAll('.edit-assessment-type-option');
    let editModalInstance = null;
    if (editAssessmentModalEl) {
        editModalInstance = new bootstrap.Modal(editAssessmentModalEl);
    }
    let elementToUpdate = null;

    /**
     * --- This is the function that hides/shows the fields ---
     * It is now case-insensitive.
     */
    function toggleEditAssessmentFields(assessmentType) {
        // Ensure it's lowercase
        const type = (assessmentType || '').toLowerCase();

        if (type === 'activity' || type === 'assignment') {
            editDurationContainer.style.display = 'none';
            editAttemptsContainer.style.display = 'none';
            editDurationInput.required = false;
            editAttemptsInput.required = false;
        } else {
            editDurationContainer.style.display = 'block';
            editAttemptsContainer.style.display = 'block';
            editDurationInput.required = true;
            editAttemptsInput.required = true;
        }
    }

    // --- Add 'change' listener to all radio buttons in the edit modal ---
    editTypeRadios.forEach(radio => {
        radio.addEventListener('change', () => toggleEditAssessmentFields(radio.value));
    });

    // This runs WHEN THE EDIT MODAL IS ABOUT TO OPEN to fill the form
    if (editAssessmentModalEl) {
        editAssessmentModalEl.addEventListener('show.bs.modal', async function (event) {
            elementToUpdate = event.relatedTarget.closest('.assessment-item');
            const assessmentId = event.relatedTarget.dataset.id;

            // 1. Clear old radio button selections
            editTypeRadios.forEach(radio => radio.checked = false);

            try {
                const response = await fetch(`../ajax/get_assessment_details.php?id=${assessmentId}`);
                const result = await response.json();

                if (result.success) {
                    const data = result.data;

                    // --- THIS IS THE FIX ---
                    // Force the type from the database (e.g., "Activity") to be lowercase
                    const assessmentType = (data.type || '').toLowerCase();

                    // Fill the form fields
                    document.getElementById('editAssessmentId').value = data.id;
                    document.getElementById('editAssessmentTitle').value = data.title;
                    tinymce.get('editAssessmentDesc').setContent(data.description || '');
                    document.getElementById('editAssessmentDuration').value = data.duration_minutes;
                    document.getElementById('editAssessmentAttempts').value = data.max_attempts;
                    document.getElementById('editAssessmentCategory').value = data.category_id;

                    // 2. Find and check the correct radio button
                    const typeRadio = document.querySelector(`#editAssessmentModal input[name="type"][value="${assessmentType}"]`);
                    if (typeRadio) {
                        typeRadio.checked = true;
                    } else {
                        console.error('Could not find a radio button for type:', assessmentType);
                    }

                    // 3. Call the toggle function *with the lowercase type*
                    // This correctly hides the fields *before* the modal is visible.
                    toggleEditAssessmentFields(assessmentType);

                } else {
                    alert('Error fetching details: ' + (result.error || 'Unknown error'));
                    event.preventDefault();
                }
            } catch (error) {
                console.error('Fetch error:', error);
                alert('Could not load assessment details.');
                event.preventDefault();
            }
        });
    }

    // This runs WHEN YOU CLICK "SAVE CHANGES"
    if (editAssessmentForm) {
        editAssessmentForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            tinymce.triggerSave();

            const formData = new FormData(editAssessmentForm);
            const response = await fetch('../ajax/update_assessment.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                editModalInstance.hide();

                if (elementToUpdate) {
                    // Instantly update all the visible information on the page
                    const newTitle = formData.get('title');
                    const newType = (formData.get('type') || '').toLowerCase(); // Force lowercase
                    const newDuration = formData.get('duration_minutes');
                    const newAttempts = formData.get('max_attempts');
                    const newDescription = tinymce.get('editAssessmentDesc').getContent({ format: 'html' });
                    const newTypeCapitalized = newType.charAt(0).toUpperCase() + newType.slice(1);

                    elementToUpdate.querySelector('.fw-bold').textContent = newTitle;
                    elementToUpdate.querySelector('.badge').textContent = newTypeCapitalized;

                    // Conditionally update/hide duration and attempts spans
                    const detailsContainer = elementToUpdate.querySelector('.text-muted.small');

                    if (detailsContainer) { // Check if this container exists
                        if (newType === 'quiz' || newType === 'exam') {
                            // Update and show for quiz/exam
                            detailsContainer.innerHTML = `
                            <span class="me-3"><i class="bi bi-clock"></i> ${newDuration} mins</span>
                            <span><i class="bi bi-arrow-repeat"></i> ${newAttempts} attempt(s)</span>
                        `;
                            detailsContainer.style.display = 'block'; // Or 'inline-block'
                        } else {
                            // Hide for activity/assignment
                            detailsContainer.style.display = 'none';
                        }
                    }

                    // Update the hidden description content as well
                    const descriptionBody = elementToUpdate.querySelector('.collapse .card-body');
                    if (descriptionBody) {
                        descriptionBody.innerHTML = newDescription;
                    }
                }
            } else {
                alert('Error: ' + (result.error || 'Could not save changes.'));
            }
        });
    }
    // --- END: Replacement block ---

    // --- Logic for the DELETE Assessment Modal ---
    const deleteAssessmentModalEl = document.getElementById('deleteAssessmentModal');
    if (deleteAssessmentModalEl) {
        const deleteModal = bootstrap.Modal.getOrCreateInstance(deleteAssessmentModalEl);
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        let elementToDelete = null;

        // This runs WHEN THE DELETE MODAL IS ABOUT TO OPEN
        deleteAssessmentModalEl.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const assessmentId = button.dataset.id;
            const assessmentTitle = button.dataset.title;

            // Set the ID on the confirm button and find the element to remove
            confirmDeleteBtn.dataset.id = assessmentId;
            elementToDelete = button.closest('.assessment-item');

            document.getElementById('assessmentNameToDelete').textContent = assessmentTitle;
        });

        // This runs WHEN YOU CLICK THE FINAL "DELETE" BUTTON
        confirmDeleteBtn.addEventListener('click', async function () {
            const assessmentId = this.dataset.id;
            const formData = new FormData();
            formData.append('assessment_id', assessmentId);

            const response = await fetch('../ajax/delete_assessment.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                deleteModal.hide();
                // Smoothly remove the assessment from the page
                if (elementToDelete) {
                    elementToDelete.style.transition = 'opacity 0.3s ease';
                    elementToDelete.style.opacity = '0';
                    setTimeout(() => elementToDelete.remove(), 300);
                }
            } else {
                alert('Error: ' + (result.error || 'Could not delete assessment.'));
            }
        });
    }

    // TQuestion Type Selection Logic
    const questionTypeSelect = document.getElementById('question_type');
    const answerFieldsContainer = document.getElementById('answer-fields-container');

    const fieldGroups = {
        multiple_choice: document.getElementById('multiple-choice-fields'),
        true_false: document.getElementById('true-false-fields'),
        identification: document.getElementById('short-answer-fields'),
        short_answer: document.getElementById('short-answer-fields'),
        essay: document.getElementById('essay-fields')
    };

    // --- This function shows/hides the correct answer fields ---
    const updateVisibleFields = () => {
        const selectedType = questionTypeSelect.value;

        // Hide all field groups
        for (const key in fieldGroups) {
            if (fieldGroups[key]) {
                fieldGroups[key].style.display = 'none';
            }
        }

        // Show the selected one
        if (fieldGroups[selectedType]) {
            fieldGroups[selectedType].style.display = 'block';
        }
    };

    // --- Event listener for the dropdown ---
    if (questionTypeSelect) {
        questionTypeSelect.addEventListener('change', updateVisibleFields);
        // Run it once on page load
        updateVisibleFields();
    }

    // --- Event listener for the form submission ---
    const addQuestionForm = document.getElementById('add-question-form');
    if (addQuestionForm) {
        addQuestionForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(addQuestionForm);
            const submitButton = addQuestionForm.querySelector('button[type="submit"]');
            submitButton.disabled = true;

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
                        button.textContent = 'Take Assessment';
                    }
                } catch (error) {
                    console.error('Failed to start assessment:', error);
                    alert('An error occurred. Please try again.');
                    button.disabled = false;
                    button.textContent = 'Take Assessment';
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
                document.getElementById('Attempts').value = button.dataset.maxAttempts;
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
                // This now re-runs the search on the server
                loadAvailableStudents(filter);
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