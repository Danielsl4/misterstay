/**
 * MisterStay - Gestión de Reservas
 * Lógica frontend interactiva (Vanilla JavaScript)
 * Cumple con Parte 3 de la prueba técnica:
 * - Filtrado dinámico llamando a la API sin recargar
 * - Modal y validación de creación de reservas
 * - Confirmación y cancelación con feedback en tiempo real
 * - Manejo robusto de errores de red y estados de carga
 */

document.addEventListener('DOMContentLoaded', () => {
    // -------------------------------------------------------------
    // 1. Configuración de API
    // -------------------------------------------------------------
    const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
    const API_URL = `${basePath}/api/reservas`;

    // -------------------------------------------------------------
    // 2. Sistema de Notificaciones Toast
    // -------------------------------------------------------------
    const toastContainer = document.getElementById('toast-container');

    function showToast(message, type = 'success', title = '') {
        if (!toastContainer) {
            alert(message);
            return;
        }

        const defaultTitles = {
            success: 'Operación realizada',
            error: 'Ha ocurrido un error',
            info: 'Información'
        };

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.setAttribute('role', 'status');

        toast.innerHTML = `
            <div class="toast-body">
                <div class="toast-title">${escapeHtml(title || defaultTitles[type] || 'Aviso')}</div>
                <div class="toast-message">${escapeHtml(message)}</div>
            </div>
            <button type="button" class="toast-close" aria-label="Cerrar">&times;</button>
        `;

        toast.querySelector('.toast-close').addEventListener('click', () => {
            closeToast(toast);
        });

        toastContainer.appendChild(toast);

        // Auto-cierre tras 4.5 segundos
        setTimeout(() => {
            closeToast(toast);
        }, 4500);
    }

    function closeToast(toast) {
        if (!toast || toast.classList.contains('toast-closing')) return;
        toast.classList.add('toast-closing');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 200);
    }

    // -------------------------------------------------------------
    // 3. Utilidades generales
    // -------------------------------------------------------------
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    function formatCurrency(amount) {
        const num = parseFloat(amount);
        if (isNaN(num)) return '0,00 €';
        return num.toLocaleString('es-ES', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' €';
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            return `${parts[2]}/${parts[1]}/${parts[0]}`;
        }
        return dateStr;
    }

    function debounce(func, delay = 300) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

    // -------------------------------------------------------------
    // 4. Filtrado del Listado de Reservas (sin recargar)
    // -------------------------------------------------------------
    const formFiltros = document.getElementById('form-filtros');
    const tbodyReservas = document.getElementById('tbody-reservas');
    const loadingSpinner = document.getElementById('loading-spinner');
    const contadorReservas = document.getElementById('contador-reservas');
    const btnLimpiarFiltros = document.getElementById('btn-limpiar-filtros');

    // Métricas rápidas
    const statTotal = document.getElementById('stat-total');
    const statConfirmadas = document.getElementById('stat-confirmadas');
    const statPendientes = document.getElementById('stat-pendientes');
    const statCanceladas = document.getElementById('stat-canceladas');

    async function cargarReservas(filtros = {}) {
        if (!tbodyReservas) return;

        if (loadingSpinner) loadingSpinner.classList.remove('hidden');

        try {
            const params = new URLSearchParams();
            for (const [key, val] of Object.entries(filtros)) {
                if (val && val.trim() !== '') {
                    params.append(key, val.trim());
                }
            }

            const url = `${API_URL}${params.toString() ? '?' + params.toString() : ''}`;
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || `Error ${response.status} al consultar la API.`);
            }

            const reservas = await response.json();
            renderizarTabla(reservas);
            actualizarEstadisticas(reservas);
        } catch (error) {
            console.error('Error al filtrar reservas:', error);
            showToast(error.message || 'No se pudieron cargar las reservas.', 'error');
        } finally {
            if (loadingSpinner) loadingSpinner.classList.add('hidden');
        }
    }

    function renderizarTabla(reservas) {
        if (!tbodyReservas) return;

        if (!Array.isArray(reservas) || reservas.length === 0) {
            tbodyReservas.innerHTML = `
                <tr id="fila-sin-resultados">
                    <td colspan="8" class="empty-state">
                        <div class="empty-state-content">
                            <p class="empty-title">No hay reservas registradas</p>
                            <p class="empty-subtitle">No se encontraron reservas con los filtros aplicados.</p>
                        </div>
                    </td>
                </tr>
            `;
            if (contadorReservas) contadorReservas.textContent = '0 resultados';
            return;
        }

        if (contadorReservas) {
            contadorReservas.textContent = `${reservas.length} resultado${reservas.length === 1 ? '' : 's'}`;
        }

        const html = reservas.map(reserva => {
            const estadoCapitalizado = reserva.estado.charAt(0).toUpperCase() + reserva.estado.slice(1);

            const btnConfirmar = reserva.estado === 'pendiente' ? `
                <button
                    type="button"
                    class="btn-action btn-confirm btn-cambiar-estado"
                    data-id="${reserva.id}"
                    data-nuevo-estado="confirmada"
                    title="Confirmar reserva"
                >
                    Confirmar
                </button>
            ` : '';

            const btnCancelar = reserva.estado !== 'cancelada' ? `
                <button
                    type="button"
                    class="btn-action btn-cancel btn-abrir-cancelar"
                    data-id="${reserva.id}"
                    data-huesped="${escapeHtml(reserva.nombre_huesped)}"
                    title="Cancelar reserva"
                >
                    Cancelar
                </button>
            ` : '';

            return `
                <tr id="fila-reserva-${reserva.id}" data-id="${reserva.id}" data-estado="${reserva.estado}">
                    <td class="reserva-id">#${reserva.id}</td>
                    <td>
                        <div class="huesped-info">
                            <span class="huesped-nombre">${escapeHtml(reserva.nombre_huesped)}</span>
                            <span class="huesped-email">${escapeHtml(reserva.email_huesped)}</span>
                        </div>
                    </td>
                    <td>
                        <span class="alojamiento-nombre">${escapeHtml(reserva.nombre_alojamiento)}</span>
                    </td>
                    <td>${formatDate(reserva.fecha_entrada)}</td>
                    <td>${formatDate(reserva.fecha_salida)}</td>
                    <td class="text-right importe-celda">
                        <strong>${formatCurrency(reserva.importe)}</strong>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-${reserva.estado}">
                            ${estadoCapitalizado}
                        </span>
                    </td>
                    <td class="text-right acciones-celda">
                        <a href="reservas.php?id=${reserva.id}" class="btn-action btn-view" title="Ver detalle y timeline">
                            Ver
                        </a>
                        ${btnConfirmar}
                        ${btnCancelar}
                    </td>
                </tr>
            `;
        }).join('');

        tbodyReservas.innerHTML = html;
    }

    function actualizarEstadisticas(reservas) {
        if (!Array.isArray(reservas)) return;

        const total = reservas.length;
        const confirmadas = reservas.filter(r => r.estado === 'confirmada').length;
        const pendientes = reservas.filter(r => r.estado === 'pendiente').length;
        const canceladas = reservas.filter(r => r.estado === 'cancelada').length;

        if (statTotal) statTotal.textContent = total;
        if (statConfirmadas) statConfirmadas.textContent = confirmadas;
        if (statPendientes) statPendientes.textContent = pendientes;
        if (statCanceladas) statCanceladas.textContent = canceladas;
    }

    function obtenerFiltrosActuales() {
        if (!formFiltros) return {};
        const formData = new FormData(formFiltros);
        return {
            estado: formData.get('estado') || '',
            huesped: formData.get('huesped') || '',
            fecha_desde: formData.get('fecha_desde') || '',
            fecha_hasta: formData.get('fecha_hasta') || ''
        };
    }

    if (formFiltros) {
        formFiltros.addEventListener('submit', (e) => {
            e.preventDefault();
            cargarReservas(obtenerFiltrosActuales());
        });

        // Búsqueda dinámica con debounce en el campo de texto de huésped
        const inputHuesped = document.getElementById('filtro-huesped');
        if (inputHuesped) {
            inputHuesped.addEventListener('input', debounce(() => {
                cargarReservas(obtenerFiltrosActuales());
            }, 300));
        }

        // Cambio directo en selects y fechas
        const selectEstado = document.getElementById('filtro-estado');
        const inputFechaDesde = document.getElementById('filtro-fecha-desde');
        const inputFechaHasta = document.getElementById('filtro-fecha-hasta');

        [selectEstado, inputFechaDesde, inputFechaHasta].forEach(el => {
            if (el) {
                el.addEventListener('change', () => {
                    cargarReservas(obtenerFiltrosActuales());
                });
            }
        });
    }

    if (btnLimpiarFiltros) {
        btnLimpiarFiltros.addEventListener('click', () => {
            if (formFiltros) formFiltros.reset();
            cargarReservas({});
        });
    }

    // -------------------------------------------------------------
    // 5. Modal y Creación de Reservas (POST /api/reservas)
    // -------------------------------------------------------------
    const modalNuevaReserva = document.getElementById('modal-nueva-reserva');
    const btnAbrirNueva = document.getElementById('btn-abrir-nueva-reserva');
    const btnCerrarModal = document.getElementById('btn-cerrar-modal');
    const btnCancelarModal = document.getElementById('btn-cancelar-modal');
    const formNuevaReserva = document.getElementById('form-nueva-reserva');
    const modalAlertaError = document.getElementById('modal-alerta-error');
    const btnGuardarReserva = document.getElementById('btn-guardar-reserva');

    function abrirModalCrear() {
        if (!modalNuevaReserva) return;
        limpiarErroresModal();
        if (formNuevaReserva) formNuevaReserva.reset();
        modalNuevaReserva.classList.remove('hidden');

        const primerInput = document.getElementById('modal-nombre-huesped');
        if (primerInput) primerInput.focus();
    }

    function cerrarModalCrear() {
        if (!modalNuevaReserva) return;
        modalNuevaReserva.classList.add('hidden');
        limpiarErroresModal();
    }

    function limpiarErroresModal() {
        if (modalAlertaError) {
            modalAlertaError.textContent = '';
            modalAlertaError.classList.add('hidden');
        }
        document.querySelectorAll('.field-error').forEach(el => {
            el.textContent = '';
        });
        document.querySelectorAll('.form-control.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
    }

    function mostrarErrorCampo(campo, mensaje) {
        const errorSpan = document.getElementById(`error-${campo}`);
        const inputEl = document.querySelector(`[name="${campo}"]`);
        if (errorSpan) errorSpan.textContent = mensaje;
        if (inputEl) inputEl.classList.add('is-invalid');
    }

    if (btnAbrirNueva) {
        btnAbrirNueva.addEventListener('click', abrirModalCrear);
    }

    if (btnCerrarModal) {
        btnCerrarModal.addEventListener('click', cerrarModalCrear);
    }

    if (btnCancelarModal) {
        btnCancelarModal.addEventListener('click', cerrarModalCrear);
    }

    if (modalNuevaReserva) {
        modalNuevaReserva.addEventListener('click', (e) => {
            if (e.target === modalNuevaReserva) {
                cerrarModalCrear();
            }
        });
    }

    // Validación y envío del formulario de creación
    if (formNuevaReserva) {
        // Limpiar error al escribir
        formNuevaReserva.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('input', () => {
                input.classList.remove('is-invalid');
                const span = document.getElementById(`error-${input.name}`);
                if (span) span.textContent = '';
            });
        });

        formNuevaReserva.addEventListener('submit', async (e) => {
            e.preventDefault();
            limpiarErroresModal();

            const formData = new FormData(formNuevaReserva);
            const datos = {
                nombre_huesped: (formData.get('nombre_huesped') || '').trim(),
                email_huesped: (formData.get('email_huesped') || '').trim(),
                nombre_alojamiento: (formData.get('nombre_alojamiento') || '').trim(),
                fecha_entrada: formData.get('fecha_entrada') || '',
                fecha_salida: formData.get('fecha_salida') || '',
                importe: formData.get('importe') || '',
                notas: (formData.get('notas') || '').trim() || null
            };

            // Validación en el cliente antes de llamar a la API
            let hayErrores = false;

            if (!datos.nombre_huesped) {
                mostrarErrorCampo('nombre_huesped', 'El nombre del huésped es obligatorio.');
                hayErrores = true;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!datos.email_huesped) {
                mostrarErrorCampo('email_huesped', 'El email del huésped es obligatorio.');
                hayErrores = true;
            } else if (!emailRegex.test(datos.email_huesped)) {
                mostrarErrorCampo('email_huesped', 'Introduce un correo electrónico válido.');
                hayErrores = true;
            }

            if (!datos.nombre_alojamiento) {
                mostrarErrorCampo('nombre_alojamiento', 'El nombre del alojamiento es obligatorio.');
                hayErrores = true;
            }

            if (!datos.fecha_entrada) {
                mostrarErrorCampo('fecha_entrada', 'Indica la fecha de entrada.');
                hayErrores = true;
            }

            if (!datos.fecha_salida) {
                mostrarErrorCampo('fecha_salida', 'Indica la fecha de salida.');
                hayErrores = true;
            } else if (datos.fecha_entrada && datos.fecha_salida <= datos.fecha_entrada) {
                mostrarErrorCampo('fecha_salida', 'La salida debe ser posterior a la entrada.');
                hayErrores = true;
            }

            const importeNum = parseFloat(datos.importe);
            if (!datos.importe || isNaN(importeNum) || importeNum <= 0) {
                mostrarErrorCampo('importe', 'El importe debe ser un número positivo.');
                hayErrores = true;
            }

            if (hayErrores) return;

            // Envío con feedback de carga
            const btnText = btnGuardarReserva.querySelector('.btn-text');
            const btnSpinner = btnGuardarReserva.querySelector('.btn-spinner');

            try {
                btnGuardarReserva.disabled = true;
                if (btnText) btnText.classList.add('hidden');
                if (btnSpinner) btnSpinner.classList.remove('hidden');

                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(datos)
                });

                const respuestaData = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const mensajeError = respuestaData.error || `Error ${response.status} al crear la reserva.`;
                    if (modalAlertaError) {
                        modalAlertaError.textContent = mensajeError;
                        modalAlertaError.classList.remove('hidden');
                    }
                    return;
                }

                // Éxito: cerrar modal, limpiar y notificar
                cerrarModalCrear();
                showToast(`Reserva #${respuestaData.id} creada correctamente para ${datos.nombre_huesped}.`, 'success');

                // Si estamos en la página de listado, refrescar la tabla
                if (tbodyReservas) {
                    cargarReservas(obtenerFiltrosActuales());
                } else {
                    // Si se creó desde otra página, redirigir al detalle de la nueva reserva
                    window.location.href = `reservas.php?id=${respuestaData.id}`;
                }
            } catch (err) {
                console.error('Error de red al crear reserva:', err);
                if (modalAlertaError) {
                    modalAlertaError.textContent = 'Error de conexión con el servidor. Verifica tu conexión.';
                    modalAlertaError.classList.remove('hidden');
                }
            } finally {
                btnGuardarReserva.disabled = false;
                if (btnText) btnText.classList.remove('hidden');
                if (btnSpinner) btnSpinner.classList.add('hidden');
            }
        });
    }

    // -------------------------------------------------------------
    // 6. Cambio de Estado: Confirmar y Cancelar (PATCH /api/reservas/{id}/estado)
    // -------------------------------------------------------------
    const modalCancelar = document.getElementById('modal-confirmar-cancelar');
    const btnCerrarModalCancelar = document.getElementById('btn-cerrar-modal-cancelar');
    const btnAbortarCancelacion = document.getElementById('btn-abortar-cancelacion');
    const btnEjecutarCancelacion = document.getElementById('btn-ejecutar-cancelacion');
    const spanCancelarId = document.getElementById('cancelar-reserva-id');
    const spanCancelarHuesped = document.getElementById('cancelar-reserva-huesped');

    let reservaIdParaCancelar = null;

    function abrirModalCancelar(id, huesped = '') {
        reservaIdParaCancelar = id;
        if (spanCancelarId) spanCancelarId.textContent = `#${id}`;
        if (spanCancelarHuesped) spanCancelarHuesped.textContent = huesped ? `(${huesped})` : '';
        if (modalCancelar) modalCancelar.classList.remove('hidden');
    }

    function cerrarModalCancelar() {
        reservaIdParaCancelar = null;
        if (modalCancelar) modalCancelar.classList.add('hidden');
    }

    if (btnCerrarModalCancelar) btnCerrarModalCancelar.addEventListener('click', cerrarModalCancelar);
    if (btnAbortarCancelacion) btnAbortarCancelacion.addEventListener('click', cerrarModalCancelar);
    if (modalCancelar) {
        modalCancelar.addEventListener('click', (e) => {
            if (e.target === modalCancelar) cerrarModalCancelar();
        });
    }

    // Confirmar acción de cancelación
    if (btnEjecutarCancelacion) {
        btnEjecutarCancelacion.addEventListener('click', async () => {
            if (!reservaIdParaCancelar) return;

            const id = reservaIdParaCancelar;
            const btnText = btnEjecutarCancelacion.querySelector('.btn-text');
            const btnSpinner = btnEjecutarCancelacion.querySelector('.btn-spinner');

            try {
                btnEjecutarCancelacion.disabled = true;
                if (btnText) btnText.classList.add('hidden');
                if (btnSpinner) btnSpinner.classList.remove('hidden');

                const response = await fetch(`${API_URL}/${id}/estado`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ estado: 'cancelada' })
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(data.error || `Error ${response.status} al cancelar la reserva.`);
                }

                cerrarModalCancelar();
                showToast(`La reserva #${id} ha sido cancelada.`, 'success');

                // Si estamos en el detalle, recargar para actualizar datos y timeline
                const esVistaDetalle = window.location.search.includes('id=');
                if (esVistaDetalle) {
                    window.location.reload();
                } else if (tbodyReservas) {
                    cargarReservas(obtenerFiltrosActuales());
                }
            } catch (err) {
                console.error('Error al cancelar reserva:', err);
                showToast(err.message || 'No se pudo cancelar la reserva.', 'error');
            } finally {
                btnEjecutarCancelacion.disabled = false;
                if (btnText) btnText.classList.remove('hidden');
                if (btnSpinner) btnSpinner.classList.add('hidden');
            }
        });
    }

    // Delegación de eventos para botones en tablas o vistas de detalle
    document.addEventListener('click', async (e) => {
        // Abrir modal de cancelación
        const btnAbrir = e.target.closest('.btn-abrir-cancelar');
        if (btnAbrir) {
            e.preventDefault();
            const id = btnAbrir.dataset.id;
            const huesped = btnAbrir.dataset.huesped || '';
            abrirModalCancelar(id, huesped);
            return;
        }

        // Confirmar reserva directamente
        const btnConfirmar = e.target.closest('.btn-cambiar-estado');
        if (btnConfirmar) {
            e.preventDefault();
            const id = btnConfirmar.dataset.id;
            const nuevoEstado = btnConfirmar.dataset.nuevoEstado || 'confirmada';

            if (!confirm(`¿Deseas cambiar el estado de la reserva #${id} a "${nuevoEstado}"?`)) {
                return;
            }

            try {
                btnConfirmar.disabled = true;

                const response = await fetch(`${API_URL}/${id}/estado`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ estado: nuevoEstado })
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(data.error || `Error ${response.status} al actualizar la reserva.`);
                }

                showToast(`Reserva #${id} confirmada con éxito.`, 'success');

                const esVistaDetalle = window.location.search.includes('id=');
                if (esVistaDetalle) {
                    window.location.reload();
                } else if (tbodyReservas) {
                    cargarReservas(obtenerFiltrosActuales());
                }
            } catch (err) {
                console.error('Error al confirmar reserva:', err);
                showToast(err.message || 'No se pudo actualizar el estado.', 'error');
            } finally {
                btnConfirmar.disabled = false;
            }
        }
    });

    // Tecla Escape para cerrar cualquier modal abierto
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (modalNuevaReserva && !modalNuevaReserva.classList.contains('hidden')) {
                cerrarModalCrear();
            }
            if (modalCancelar && !modalCancelar.classList.contains('hidden')) {
                cerrarModalCancelar();
            }
        }
    });
});
