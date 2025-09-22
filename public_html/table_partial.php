<table class="table table-striped table-hover md-8" style="width: 100%;">
    <thead>
        <tr>
            <th class="col-md-1">編集</th>
            <?php
            $fields = $result->fetch_fields();
            $firstField = true; // 最初のフィールドかどうかを判定するフラグ
            foreach ($fields as $field):
            ?>
                <th class="<?= $firstField ? 'col-md-1' : '' ?>"><?= $field->name ?></th>
                <?php $firstField = false; ?>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td>
                    <button type="button"
                            class="btn btn-primary btn-sm edit-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#editModal"
                            data-record='<?= htmlspecialchars(json_encode($row)) ?>'
                            data-table="<?= $selectedTable ?>">
                        編集
                    </button>
                </td>
                <?php foreach ($row as $value): ?>
                    <td><?= htmlspecialchars($value) ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>