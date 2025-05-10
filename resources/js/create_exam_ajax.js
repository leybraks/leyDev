    document.addEventListener('DOMContentLoaded', () => {
        const questionsContainer = document.getElementById('questions-container');
        const addQuestionBtn = document.getElementById('add-question-btn');
        const questionTemplate = document.getElementById('question-template');
        const optionTemplate = document.getElementById('option-template');
        let questionIndex = 0;

        function escapeHTML(str) {
            if (typeof str !== 'string') return '';
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }

        function addQuestion() {
            const clone = questionTemplate.content.cloneNode(true);
            const questionBlock = clone.querySelector('.question-block');
            const questionNumberSpan = clone.querySelector('.question-number');
            const questionTextarea = clone.querySelector('textarea[name^="question_text"]');
            const pointsInput = clone.querySelector('input[name^="question_points"]');
            const addOptionBtn = clone.querySelector('.add-option-btn');
            const removeQuestionBtn = clone.querySelector('.remove-question-btn');

            const currentQIndex = questionIndex;
            questionBlock.dataset.questionIndex = currentQIndex;
            if(questionNumberSpan) questionNumberSpan.textContent = currentQIndex + 1;
            
            const qTextLabel = questionBlock.querySelector(`label[for="q_text_Q_IDX"]`);
            if(questionTextarea) {
                questionTextarea.name = `question_text[${currentQIndex}]`;
                questionTextarea.id = `q_text_${currentQIndex}`;
                if(qTextLabel) qTextLabel.setAttribute('for', questionTextarea.id);
            }
            
            const qPointsLabel = questionBlock.querySelector(`label[for="q_points_Q_IDX"]`);
            if(pointsInput) {
                pointsInput.name = `question_points[${currentQIndex}]`;
                pointsInput.id = `q_points_${currentQIndex}`;
                if(qPointsLabel) qPointsLabel.setAttribute('for', pointsInput.id);
            }
            
            if(addOptionBtn) addOptionBtn.addEventListener('click', () => addOption(questionBlock));
            if(removeQuestionBtn) removeQuestionBtn.addEventListener('click', () => removeQuestion(questionBlock));

            questionsContainer.appendChild(clone);
            addOption(questionBlock); 
            addOption(questionBlock);
            questionIndex++;
        }

        function addOption(questionBlock) {
            const optionsContainer = questionBlock.querySelector('.options-container');
            if (!optionsContainer) return;

            const currentQIndex = questionBlock.dataset.questionIndex;
            const optionCount = optionsContainer.querySelectorAll('.option-group').length;
            const currentOptIndex = optionCount;

            const clone = optionTemplate.content.cloneNode(true);
            const optionGroup = clone.querySelector('.option-group');
            const radioInput = clone.querySelector('input[type="radio"]');
            const textInput = clone.querySelector('input[type="text"]');
            const textInputLabel = clone.querySelector('label[for^="option_"]'); 
            const radioLabel = clone.querySelector('label.radio-label'); 

            if(radioInput) {
                radioInput.name = `correct_option[${currentQIndex}]`;
                radioInput.value = currentOptIndex;
                radioInput.id = `correct_q${currentQIndex}_opt${currentOptIndex}`;
            }
            if(textInput) {
                textInput.name = `option_text[${currentQIndex}][]`;
                textInput.id = `option_text_q${currentQIndex}_opt${currentOptIndex}`; 
                textInput.placeholder = `Texto opción ${currentOptIndex + 1}`;
            }
            if(textInputLabel) textInputLabel.setAttribute('for', textInput.id); 
            if(radioLabel) radioLabel.setAttribute('for', radioInput.id); 


            const removeOptionBtn = clone.querySelector('.remove-option-btn');
            if(removeOptionBtn) removeOptionBtn.addEventListener('click', () => removeOption(optionGroup));

            optionsContainer.appendChild(clone);
        }

        function removeQuestion(questionBlock) {
            if (confirm('¿Seguro que quieres eliminar esta pregunta y todas sus opciones?')) {
                questionBlock.remove();
            }
        }

        function removeOption(optionGroup) {
            const optionsContainer = optionGroup.closest('.options-container');
            if (optionsContainer && optionsContainer.querySelectorAll('.option-group').length > 2) {
                optionGroup.remove();
            } else {
                alert('Una pregunta debe tener al menos dos opciones.');
            }
        }

        if (addQuestionBtn) addQuestionBtn.addEventListener('click', addQuestion);
        addQuestion(); 

        const createExamForm = document.getElementById('create-exam-form');
        const ajaxMessageDiv = document.getElementById('exam-creation-ajax-message');

        if (createExamForm && ajaxMessageDiv) {
            createExamForm.addEventListener('submit', function(event) {
                event.preventDefault();
                console.log("CREATE_EXAM_AJAX: Submit interceptado.");

                ajaxMessageDiv.textContent = '';
                ajaxMessageDiv.style.display = 'none';
                ajaxMessageDiv.className = 'message'; 

                const submitButton = createExamForm.querySelector('button[type="submit"].btn-submit');
                const originalButtonText = submitButton ? submitButton.textContent : 'Guardar Examen';
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Guardando Examen...';
                }

                const formData = new FormData(createExamForm);
                
                fetch('../Scripts/handle_exam_create.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json().then(data => ({ ok: response.ok, status: response.status, data })))
                .then(result => {
                    const data = result.data;
                    console.log("CREATE_EXAM_AJAX: Respuesta JSON:", data);
                    ajaxMessageDiv.style.display = 'block';

                    if (data.success) {
                        ajaxMessageDiv.textContent = data.message;
                        ajaxMessageDiv.classList.add('success-message');
                        if (data.redirect_url) {
                            setTimeout(() => { window.location.href = data.redirect_url; }, 2000);
                        }
                    } else {
                        ajaxMessageDiv.textContent = data.message || 'Error al crear el examen.';
                        ajaxMessageDiv.classList.add('error-message');
                        if (data.errors && Object.keys(data.errors).length > 0) {
                            let errorListHTML = '<ul>';
                            for (const key in data.errors) {
                                errorListHTML += `<li>${escapeHTML(data.errors[key])}</li>`;
                            }
                            errorListHTML += '</ul>';
                            ajaxMessageDiv.innerHTML = `<strong>${escapeHTML(data.message)}</strong>${errorListHTML}`;
                        }
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.textContent = originalButtonText;
                        }
                    }
                })
                .catch(error => {
                    console.error('CREATE_EXAM_AJAX: Error en Fetch:', error);
                    ajaxMessageDiv.textContent = 'Error de conexión o respuesta inesperada del servidor.';
                    ajaxMessageDiv.style.display = 'block';
                    ajaxMessageDiv.classList.add('error-message');
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalButtonText;
                    }
                });
            });
        } else {
            if(!createExamForm) console.error("Formulario #create-exam-form no encontrado para AJAX.");
            if(!ajaxMessageDiv) console.error("Div #exam-creation-ajax-message no encontrado.");
        }
    });