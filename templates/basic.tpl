<table width="100%">
	<tr>
		<td><h1>&Uacute;ltimas 100 notas</h1></td>
		<td align="right">
			{button href="PIZARRA reemplace este texto por su nota" body="Escriba una nota que no exeda los 130 caracteres en el asunto y envie este email" caption="Nueva nota"}
		</td>
	</tr>
</table>

{space10}

{foreach from=$tweets item=tweet}
	<font color="gray"><small>{$tweet['date']|date_format:"%e/%m/%Y %I:%M %p"}</small></font>
	<br/>
	{$tweet['text']|ucfirst}
	{space5}
{/foreach}
