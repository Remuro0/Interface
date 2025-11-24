<?php
session_start();
require_once '../auth.php';
requireAuth();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Создать новую таблицу</title>
    <link rel="stylesheet" href="../css/create_table.css">
</head>
<body>
    <div class="form-box">
        <h2>Создать новую таблицу</h2>
        <form method="POST" action="../actions/create_table.php">
            <label for="table_name">Имя таблицы:</label>
            <input type="text" id="table_name" name="table_name" required>

            <label for="column_count">Количество столбцов:</label>
            <input type="number" id="column_count" name="column_count" min="1" max="50" value="5" required>

            <div id="columnsContainer">
                <!-- Здесь будут генерироваться поля для столбцов -->
            </div>

            <div style="margin-top: 20px; display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" id="fillDataToggle" name="fill_data" style="width: auto; height: auto;">
                <label for="fillDataToggle" style="color: #c7b8ff;">Хотите заполнить данные?</label>
            </div>

            <div id="dataFields" style="display: none; margin-top: 20px;">
                <label>Введите данные (3 строки):</label>
                <div id="dataRows">
                    <!-- Здесь будут генерироваться строки данных -->
                </div>
            </div>

            <div class="buttons">
                <button type="submit">Создать таблицу</button>
                <button type="button" onclick="window.location.href='../pages/view_db.php'">Отмена</button>
            </div>
        </form>
    </div>

    <script>
        // Список типов данных
        const dataTypes = [
            { value: 'INT', label: 'INT' },
            { value: 'VARCHAR', label: 'VARCHAR' },
            { value: 'TEXT', label: 'TEXT' },
            { value: 'DATE', label: 'DATE' },
            { value: 'DATETIME', label: 'DATETIME' },
            { value: 'DECIMAL', label: 'DECIMAL' },
            { value: 'BOOLEAN', label: 'BOOLEAN' },
            { value: 'JSON', label: 'JSON' }
        ];

        // Генерация полей для столбцов
        function generateColumnFields(count) {
            const container = document.getElementById('columnsContainer');
            container.innerHTML = '';
            for (let i = 1; i <= count; i++) {
                const field = document.createElement('div');
                field.className = 'column-field';
                field.innerHTML = `
                    <div style="display: flex; flex-wrap: wrap; gap: 10px; margin: 5px 0; align-items: center;">
                        <input type="text" name="column_${i}" placeholder="Название столбца" required style="flex: 1; min-width: 120px; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 4px; font-size: 14px;">
                        <select name="type_${i}" required style="width: auto; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 4px; font-size: 14px;">
                            ${dataTypes.map(opt => `<option value="${opt.value}">${opt.label}</option>`).join('')}
                        </select>
                        <input type="text" name="length_${i}" placeholder="Длина/значение" style="width: 80px; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 4px; font-size: 14px;">
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px; margin: 5px 0; align-items: center;">
                        <select name="default_${i}" style="width: auto; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 4px; font-size: 14px;">
                            <option value="">По умолчанию</option>
                            <option value="NULL">NULL</option>
                            <option value="CURRENT_TIMESTAMP">CURRENT_TIMESTAMP</option>
                            <option value="0">0</option>
                            <option value="''">''</option>
                        </select>
                        <select name="collation_${i}" style="width: auto; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 4px; font-size: 14px;">
                            <option value="">Сравнение</option>
                            <option value="utf8mb4_general_ci">utf8mb4_general_ci</option>
                            <option value="utf8mb4_unicode_ci">utf8mb4_unicode_ci</option>
                        </select>
                        <select name="attributes_${i}" style="width: auto; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 4px; font-size: 14px;">
                            <option value="">Атрибуты</option>
                            <option value="UNSIGNED">UNSIGNED</option>
                            <option value="ZEROFILL">ZEROFILL</option>
                        </select>
                        <select name="null_${i}" style="width: auto; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 4px; font-size: 14px;">
                            <option value="NOT NULL">NOT NULL</option>
                            <option value="NULL">NULL</option>
                        </select>
                        <input type="text" name="comment_${i}" placeholder="Комментарий" style="flex: 1; min-width: 150px; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 4px; font-size: 14px;">
                    </div>
                `;
                container.appendChild(field);
            }
        }

        // Генерация полей для данных
        function generateDataFields(count) {
            const container = document.getElementById('dataRows');
            container.innerHTML = '';
            for (let row = 1; row <= 3; row++) {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'data-row';
                for (let col = 1; col <= count; col++) {
                    rowDiv.innerHTML += `
                        <input type="text" name="data_${row}_${col}" placeholder="Значение ${row}.${col}" style="flex: 1; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 4px; font-size: 14px;">
                    `;
                }
                container.appendChild(rowDiv);
            }
        }

        // Обработчик изменения количества столбцов
        document.getElementById('column_count').addEventListener('input', function() {
            const count = parseInt(this.value) || 5;
            generateColumnFields(count);
            if (document.getElementById('fillDataToggle').checked) {
                generateDataFields(count);
            }
        });

        // Обработчик тумблера "Хотите заполнить данные?"
        document.getElementById('fillDataToggle').addEventListener('change', function() {
            const count = parseInt(document.getElementById('column_count').value) || 5;
            const dataFields = document.getElementById('dataFields');
            if (this.checked) {
                dataFields.style.display = 'block';
                generateDataFields(count);
            } else {
                dataFields.style.display = 'none';
            }
        });

        // Инициализация при загрузке страницы
        window.addEventListener('DOMContentLoaded', function() {
            generateColumnFields(5); // По умолчанию 5 столбцов
        });
    </script>
</body>
</html>