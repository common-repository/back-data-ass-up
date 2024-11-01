<div class="wrap">
	<h2>back data ass up <span style="font-size:50%">version <?php echo $plugin_version; ?></span></h2>
	
	<div style="float:left; width: 300px">
		<form method="post">
			<ul id="backdataassup_tables_select">
				<li><a href="?page=backdataassup&select=all">Select All</a></li>
				<li><a href="?page=backdataassup&select=none">Select None</a></li>
			</ul>
			<ol id="backdataassup_tables_list">
				<?php foreach( $tables as $table ): ?>
				<li>
					<label>
						<input type="checkbox" 
							   name="backdataassup_tables[]" 
							   value="<?php echo htmlentities( $table->table ); ?>"
							   <?php echo $table->checked; ?>/> <?php echo htmlentities( $table->table ); ?>
						<span style="display:block; font-size:50%; color:#999">
							<?php echo htmlentities( $table->Rows ); ?> Rows | 
							<?php echo htmlentities( $table->size ); ?>
						</small>
					</label>
				</li>
				<?php endforeach; ?>
			</ol>
			
			<input type="submit" name="backdataassup_save" value="Save Changes"/>
			<input type="submit" name="backdataassup_now" value="Backup NOW"/>
		</form>
	</div>
	
	<div style="margin-left:305px">
		<!-- <img src="<?php echo WP_PLUGIN_URL; ?>/back-data-ass-up/screenshot-1.png"/> -->
		<form method="post">
			<h3>Settings</h3>
			<ul>
				<li>
					Last Backup: <?php echo $lastrun; ?>
				</li>
				
				<li>
					<input type="checkbox" name="backdataassup_saveoptions[email][checked]" value="" <?php echo $options->email->checked; ?>/>
					Email Backup To<br/>
					<input type="text" name="backdataassup_saveoptions[email][value]" value="<?php echo htmlentities( $options->email->value ); ?>" class="code" size="70"/>
				</li>
				
				<li>
					<input type="checkbox" name="backdataassup_saveoptions[file][checked]" value="" <?php echo $options->file->checked; ?>/>
					Save Backup To Server<br/>
					<input type="text" name="backdataassup_saveoptions[file][value]" value="<?php echo htmlentities( $options->file->value ); ?>" class="code" size="70"/>
				</li>
				
				<li>
					Cron URL<br/>
					<input type="text" name="backdataassup_saveoptions[cronURL][value]" value="<?php echo htmlentities( $options->cronURL->value ); ?>" class="code" size="70"/>
				</li>
				
				<li>
					Compression Options
					<?php foreach( $compression as $key=>$selected ): ?>
					<label>
						<input type="radio" name="backdataassup_compression" <?php echo $selected; ?> value="<?php echo $key; ?>"/>
						<?php echo $key; ?>
					</label>
					<?php endforeach; ?>
				</li>
			</ul>
			
			<input type="submit" value="Save Options"/>
			
			<h3>Backup Files</h3>
			
			<table id="backdataassup_backups" class="widefat" style="clear:none">
				<thead>
					<tr>
						<th>File Name</th>
						<th>Created</th>
						<th>Size</th>
						<th>Action</th>
						<th>Bulk</th>
					</tr>
				</thead>
				<?php foreach( $db_files as $db_file ): ?>
				<tr>
					<td><a href="?page=databackup&file=<?php echo $db_file->file; ?>"><?php echo $db_file->file; ?></a></td>
					<td><?php echo $db_file->created; ?></td>
					<td><?php echo $db_file->size; ?></td>
					<td><input type="submit" value="Delete" name="backdataassup_delete[<?php echo $db_file->file; ?>]"/></td>
					<td><input type="checkbox" name="backdataassup_bulk[]" value="<?php echo $db_file->file; ?>"/></td>
				</tr>
				<?php endforeach; ?>
			</table>
			
			<select name="backdataassup_bulkaction">
				<option value="">Bulk Actions</option>
				<option value="delete">Delete</option>
			</select>
			
			<input type="submit" value="Apply" name=""/>
		</form>
	</div>
</div>