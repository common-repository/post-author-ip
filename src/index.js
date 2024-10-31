/**
 * WordPress dependencies
 */
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';
import { Disabled, TextControl } from '@wordpress/components';
import { PluginPostStatusInfo } from '@wordpress/edit-post';
import { useSelect } from '@wordpress/data';

const PostAuthorIP = () => {
	// @TODO Use the actual potentially-filtered meta key name.
	const postAuthorIP = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' )[
		'c2c-post-author-ip'
	] );

	console.log(postAuthorIP);
	return (
		<PluginPostStatusInfo>
		<Disabled
			className="post-author-ip-disabled"
		>
			<TextControl
				label={ __( 'Author IP Address', 'post-author-ip' ) }
				className="post-author-ip"
				value={ postAuthorIP }
				onChange={
					() => {}
				}
			/>
		</Disabled>
		</PluginPostStatusInfo>
	);
}

registerPlugin( 'post-author-ip', {
	render: PostAuthorIP
} );
