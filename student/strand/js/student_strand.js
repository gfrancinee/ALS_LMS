document.addEventListener('DOMContentLoaded', () => {

    // SECTION 1: ELEMENT REFERENCES
    const strandId = window.strandId;
    const mediaModal = document.getElementById('mediaModal');
    const assessmentListContainer = document.getElementById('assessmentList');
    const participantListContainer = document.getElementById('participantList');

    // SECTION 2: CORE FUNCTIONS

    /**
     * Fetches and displays the list of assessments for the student.
     * Students can view and take assessments from this list.
     */
    function refreshAssessmentList() {
        if (!strandId || !assessmentListContainer) return;

        // This PHP file should be designed to show a student-friendly list.
        // For example, it should include a "Take Quiz" button instead of "Manage Questions".
        fetch(`../../ajax/get-assessments-student.php?strand_id=${strandId}`)
            .then(response => response.text())
            .then(html => {
                assessmentListContainer.innerHTML = html;
            })
            .catch(err => {
                console.error('Failed to load assessments:', err);
                assessmentListContainer.innerHTML = '<div class="alert alert-danger">Could not load assessments.</div>';
            });
    }

    /**
     * Fetches and displays the list of participants in the strand.
     * The "Remove" button is not included in the generated HTML.
     */
    async function refreshParticipantList() {
        if (!participantListContainer) return;
        participantListContainer.innerHTML = '<p>Loading participants...</p>';
        try {
            // This PHP endpoint should only return the names, without any admin controls.
            const response = await fetch(`../../ajax/get_strand_participants.php?strand_id=${strandId}`);
            const participants = await response.json();

            if (participants.error) {
                throw new Error(participants.error);
            }

            if (participants.length === 0) {
                participantListContainer.innerHTML = '<div class="alert alert-info">You are the first one here!</div>';
                return;
            }

            // Create a simple list of names for the student view
            let html = '<ul class="list-group">';
            participants.forEach(p => {
                html += `<li class="list-group-item">${p.fname} ${p.lname}</li>`;
            });
            html += '</ul>';
            participantListContainer.innerHTML = html;

        } catch (error) {
            console.error('Failed to refresh participant list:', error);
            participantListContainer.innerHTML = '<p class="text-danger">Could not load participant list.</p>';
        }
    }


    // SECTION 3: EVENT LISTENERS

    /**
     * Handles the universal media modal for viewing files (images, videos, PDFs, etc.).
     * This logic is the same for both teachers and students.
     */
    if (mediaModal) {
        mediaModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const type = button.getAttribute('data-type');
            const url = button.getAttribute('data-url');
            const label = button.getAttribute('data-label');

            const modalTitle = document.getElementById('mediaModalLabel');
            const modalBody = document.getElementById('mediaModalBody');
            const downloadLink = document.getElementById('mediaDownloadLink');

            modalTitle.textContent = label;

            let contentHTML = '';
            switch (type) {
                case 'image':
                    contentHTML = `<img src="${url}" class="img-fluid w-100" alt="${label}">`;
                    break;
                case 'video':
                    contentHTML = `<video controls autoplay class="w-100" style="max-height: 70vh;"><source src="${url}" type="video/mp4">Your browser does not support the video tag.</video>`;
                    break;
                case 'audio':
                    contentHTML = `<audio controls autoplay class="w-100"><source src="${url}" type="audio/mpeg">Your browser does not support the audio element.</audio>`;
                    break;
                case 'file': // Primarily for PDFs
                    contentHTML = `<iframe src="${url}" style="width:100%; height:75vh; border:0;"></iframe>`;
                    break;
                default:
                    contentHTML = `<p class="text-center">Cannot preview this file type directly.</p><p class="text-center">Please use the download button.</p>`;
            }
            modalBody.innerHTML = contentHTML;
            downloadLink.href = url;
        });

        // Stops media from playing in the background after the modal is closed.
        mediaModal.addEventListener('hide.bs.modal', function () {
            document.getElementById('mediaModalBody').innerHTML = '';
        });
    }


    // SECTION 4: INITIAL PAGE LOAD & TAB CONTROLS

    // Load initial data and set up listeners for tab clicks.
    const assessmentsTabBtn = document.querySelector('a[href="#assessments"]');
    if (assessmentsTabBtn) {
        // Load the assessments when the page first loads
        refreshAssessmentList();
        // Also refresh the list if the user clicks on the tab (in case they navigate away and back)
        assessmentsTabBtn.addEventListener('shown.bs.tab', refreshAssessmentList);
    }

    const participantsTabBtn = document.querySelector('a[href="#participants"]');
    if (participantsTabBtn) {
        // Load participants only when the tab is clicked to save initial loading time.
        participantsTabBtn.addEventListener('shown.bs.tab', refreshParticipantList);
    }
});