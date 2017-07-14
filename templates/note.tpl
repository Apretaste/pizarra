{space5}

<font color="gray">
	<small>
		{link href="PERFIL @{$note['username']}" caption="@{$note['username']}"},
		{$note['location']},
		{if $note['gender'] eq "M"}<font color="#4863A0">M</font>{/if}
		{if $note['gender'] eq "F"}<font color=#F778A1>F</font>{/if}
		{if $note['picture']}[foto]{/if}
		{separator}
		<font color="gray">{$note['inserted']|date_format:"%e/%m %l:%M %p"}</font>
	</small>
</font>
<br/>
<big><big>{$note['text']|replace_url}</big></big>
<br/>
<small>
	<font color="green">+</font>&nbsp;{link href="PIZARRA LIKE {$note['id']}" caption="Bueno" body="Envie este email tal como esta para expresar gusto por este post de este usuario" wait="false"}
	({$note['likes']})
	{separator}
	<font color="red">-</font>&nbsp;{link href="PIZARRA UNLIKE {$note['id']}" caption="Malo" body="Envie este email tal como esta para expresar que este post no le gusta" wait="false"}
	({$note['unlikes']})
</small>

{space5}

<hr/>
<table width="100%">
{foreach from=$note['comments'] item=comment}
	<tr>
		<td>
			{space5}
			<font color="gray">
				<small>
					{link href="PERFIL @{$comment->username}" caption="@{$comment->username}"},
					{if $comment->gender eq "M"}<font color="#4863A0">M</font>{/if}
					{if $comment->gender eq "F"}<font color=#F778A1>F</font>{/if}
					{if $comment->picture}[foto]{/if}
					{separator}
					<font color="gray">{$comment->inserted|date_format:"%e/%m %l:%M %p"}</font>
				</small>
			</font>
			<br/>
			{$comment->text|replace_url}
			<br/>
			{space5}
		</td>
	</tr>
{/foreach}
</table>

<center>
	{button href="PIZARRA {$note['id']}* " body="Escriba un comentario en el asunto despues de la palabra PIZARRA y envie este email" caption="Comentar" popup="true" wait="false" desc="Inserte una comentario a esta nota"}
</center>
