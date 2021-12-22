<div class="content-element__content<% if $StyleVariant %> {$StyleVariant}<% end_if %>">
    <% include ElementIframeTitle %>
<div class="outer<% if $IsDynamic %> iframe-resizer<% end_if %><% if $IsResponsive %> responsive-iframe is-{$IsResponsive.XML}<% end_if %>">
        <% if $IsLazy %><noscript class="loading-lazy"><% end_if %>
            <iframe
                <% if $AlternateContent %>title="{$AlternateContent.XML}"<% end_if %>
                class="<% if $IsResponsive %> responsive-item<% end_if %>"
                width="{$IframeWidth.XML}"
                height="{$IframeHeight.XML}"
                id="{$Anchor.XML}-frame"
                <% if $DefaultAllowAttributes %>allow="$DefaultAllowAttributes"<% end_if %>
                <% if $IsLazy %>loading="lazy"<% end_if %>
                src="{$URL.LinkURL.XML}"
                frameborder="0">
            </iframe>
        <% if $IsLazy %></noscript><% end_if %>
    </div>
</div>
