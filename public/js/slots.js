// public/js/slots.js
document.addEventListener("DOMContentLoaded", () => {
  const dateInput = document.getElementById("date");
  const specialtyInput = document.getElementById("specialty");
  const horariosDiv = document.getElementById("horarios");
  const hiddenTime = document.getElementById("appointment_time");
  const form = document.getElementById("patientForm");

  // escucha también el custom event 'specialty:changed' enviado desde form.js
  specialtyInput.addEventListener("change", cargarHorarios);
  dateInput.addEventListener("change", cargarHorarios);
  document.addEventListener('specialty:changed', cargarHorarios);

  async function cargarHorarios() {
    const date = dateInput.value;
    const spec = specialtyInput.value;
    hiddenTime.value = ""; // limpiar selección previa
    if (!date || !spec) {
      horariosDiv.innerHTML = "<p class='text-muted'>Selecciona especialidad y fecha</p>";
      return;
    }

    horariosDiv.innerHTML = "<p>Cargando horarios…</p>";
    try {
      const res = await fetch(`../api/get_slots.php?date=${encodeURIComponent(date)}&specialty_id=${encodeURIComponent(spec)}`, {cache:'no-store'});
      const data = await res.json();

      horariosDiv.innerHTML = "";
      if (!data.times || data.times.length === 0) {
        horariosDiv.innerHTML = "<p>No hay horarios disponibles</p>";
        return;
      }

      const row = document.createElement("div");
      row.className = "d-flex flex-wrap";

      // calcular ahora local (navegador)
      const now = new Date();

      data.times.forEach(slot => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "btn btn-outline-primary me-2 mb-2 slot-btn";
        btn.textContent = slot.time;
        btn.dataset.time = slot.time;

        // determinar si el slot ya pasó según la fecha seleccionada y la hora actual del navegador
        let isPastByClientClock = false;
        try {
          const selectedDateParts = date.split("-"); // date en formato Y-m-d
          const slotParts = slot.time.split(":");
          const slotDateTime = new Date(
            parseInt(selectedDateParts[0],10),
            parseInt(selectedDateParts[1],10)-1,
            parseInt(selectedDateParts[2],10),
            parseInt(slotParts[0],10),
            parseInt(slotParts[1],10),
            0
          );
          if (slotDateTime <= now) isPastByClientClock = true;
        } catch (err) {
          // si falla el parse, no marcar como pasado por cliente
          isPastByClientClock = false;
        }

        // manejar estados incluyendo 'past' que viene del servidor
        const st = slot.status;
        if ((st === 'available' && !isPastByClientClock) || (st === 'available' && !isPastByClientClock)) {
          // disponible: botón interactivo
          btn.disabled = false;
        }
        if (st === 'booked') {
          btn.disabled = true;
          btn.className = "btn slot-btn booked me-2 mb-2";
          btn.title = "Ocupado";
        } else if (st === 'held') {
          btn.disabled = true;
          btn.className = "btn slot-btn held me-2 mb-2";
          btn.title = "Reservado temporalmente";
        } else if (st === 'blocked') {
          btn.disabled = true;
          btn.className = "btn slot-btn blocked me-2 mb-2";
          btn.title = "Horario bloqueado";
        } else if (st === 'past' || isPastByClientClock) {
          // tratar 'past' como ocupado/pasado
          btn.disabled = true;
          btn.className = "btn slot-btn booked me-2 mb-2";
          btn.title = "Horario pasado";
        }

        btn.addEventListener("click", () => {
          if (btn.disabled) return;
          // seleccionar este y ocultar los demás
          document.querySelectorAll("#horarios .slot-btn").forEach(b => {
            if (b !== btn) b.style.display = "none";
            b.classList.remove("selected","btn-primary");
          });
          btn.classList.add("selected","btn-primary");
          btn.style.display = "";
          // crear/actualizar hidden input
          hiddenTime.value = btn.dataset.time;
        });

        row.appendChild(btn);
      });

      horariosDiv.appendChild(row);

      // emitir evento para que otros scripts (ej: form.js) puedan reaccionar si hace falta
      document.dispatchEvent(new CustomEvent('slots:loaded', { detail: { date, specialty: spec } }));

    } catch (e) {
      horariosDiv.innerHTML = "<p class='text-danger'>Error cargando horarios</p>";
      console.error(e);
    }
  }

  // cargar al inicio si hay valores pre-llenados
  if (dateInput.value && specialtyInput.value) {
    setTimeout(cargarHorarios, 200);
  }
});
