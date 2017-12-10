<center>
	<h1>Nube de temas</h1>
	{foreach from=$topics item=topic}
		{link href="PIZARRA {$topic->name}" caption="#{$topic->name}" style="font-size:{$topic->fontSize}px; color:{$topic->color};"}
		&nbsp;
	{/foreach}

	{space30}

	<h1>Usuarios mas populares</h1>
	{foreach from=$users item=user}
		<div style="display:inline-block; margin:0px 10px 20px 10px;">
			{img src="{$user->picture_public}" alt="{$user->username}" width="80" height="80"}<br/>
			{link href="PIZARRA @{$user->username}" caption="@{$user->username}" style="font-size:small;"}
		</div>
	{/foreach}
</center>
