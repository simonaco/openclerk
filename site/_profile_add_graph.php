<div id="edit_graph_form">
<form action="<?php echo htmlspecialchars(url_for('profile_add_graph')); ?>" method="post">
<table class="form">
<tr>
	<th>Category:</th>
	<td><select name="category" id="graph_category">
		<option id="graph_category_template">Loading...</option>
	</select></td>
</tr>
<tr>
	<th>Graph type:</th>
	<td><select name="type" id="graph_type">
		<option id="graph_type_template">Loading...</option>
	</select></td>
</tr>
<tr id="add_graph_arg0" style="display:none;">
	<th>Argument:</th>
	<td><select name="arg0" id="graph_arg0">
		<option value="" id="graph_arg0_template">Loading...</option>
	</select></td>
</tr>
<tr id="add_graph_string0" style="display:none;">
	<th>Argument:</th>
	<td><input name="string0" id="graph_string0" size="32" maxlength="128" value="Loading..."></td>
</tr>
<?php
$size_options = array('width' => 'Width', 'height' => 'Height');
foreach ($size_options as $size_key => $size_value) { ?>
<tr>
	<th><?php echo htmlspecialchars($size_value); ?>:</th>
	<td><select name="<?php echo htmlspecialchars($size_key); ?>">
		<?php
			$options = array(1 => "Small", 2 => "Medium", 4 => "Large", 5 => "Larger", 6 => "Very Large", 8 => "Huge", 10 => "Massive");
			foreach ($options as $key => $value) {
				echo '<option value="' . htmlspecialchars($key) . '"' . (get_site_config('default_user_graph_' . $size_key, 4) == $key ? ' selected' : '') . '>' . htmlspecialchars($value) . ' (' . number_format(get_site_config('default_graph_' . $size_key) * $key) . 'px)</option>';
			}
		?>
	</select></td>
</tr>
<?php } ?>
<tr id="add_graph_days" style="display:none;">
	<th>Days:</th>
	<td><select name="days">
<?php foreach (get_permitted_days() as $key => $days) { ?>
		<option value="<?php echo htmlspecialchars($days['days']); ?>"<?php echo get_site_config('default_user_graph_days') == $days['days'] ? " selected" : ""; ?>><?php echo htmlspecialchars($days['title']); ?></option>
<?php } ?>
	</select></td>
</tr>
<tr id="add_graph_delta" style="display:none;">
	<th>Delta:</th>
	<td><select name="delta">
<?php foreach (get_permitted_deltas() as $key => $days) { ?>
		<option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($days['description']); ?></option>
<?php } ?>
	</select></td>
</tr>
<tr id="add_graph_technical" style="display:none;">
	<th>Technical:</th>
	<td><select name="technical" id="graph_technical">
		<option value="">(none)</option>
		<option id="graph_technical_template">Loading...</option>
	</select>
<?php if (!$user['is_premium']) { ?>
	<div class="tip" id="premium_warning" style="display:none;">This technical analysis tool requires a <a href="<?php echo htmlspecialchars(url_for('premium')); ?>" class="premium">premium account</a>.</div>
<?php } ?>
	</td>
</tr>
<tr id="add_graph_period" style="display:none;">
	<th></th>
	<td>
		<label>Period: <input type="text" name="period" value="10" size="6"> days</label>
	</td>
</tr>
<tr>
	<td colspan="2" class="buttons">
		<input type="hidden" name="page" value="<?php echo htmlspecialchars($page_id); ?>">
		<input type="submit" value="Add graph">
		<input type="hidden" name="id" value="">

		<div class="managed-notice">
		<?php if ($graph_page['is_managed'] && $user['graph_managed_type'] == 'managed') { ?>
			These graphs are currently <a href="<?php echo htmlspecialchars(url_for('wizard_reports')); ?>">managed based on your portfolio preferences</a>.
		<?php } else { ?>
			You can also <a href="<?php echo htmlspecialchars(url_for('wizard_reports')); ?>">add graphs based on your portfolio preferences</a>.
		<?php } ?>
		</div>

	</td>
</tr>
</table>

<div id="graph_description">Select an option</div>
</form>
</div>

<script type="text/javascript">
function user_has_premium() {
	return <?php echo $user['is_premium'] ? "true" : "false"; ?>;
}
</script>
