/**
 * WordPress dependencies
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import {
	Button,
	PanelBody,
	Placeholder,
	TextControl,
} from '@wordpress/components';
// The default export is used because the named `ServerSideRender` export is not available in WordPress 6.8.
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Internal dependencies
 */
import metadata from './block.json';

const {
	title,
	label_amount: labelAmount,
	label_add_form: labelAddForm,
} = window.pronamic_payment_form;

/**
 * Edit payment form block.
 *
 * @param {Object}                         props               Block props.
 * @param {Object}                         props.attributes    Block attributes.
 * @param {( attributes: Object ) => void} props.setAttributes Function to update the block attributes.
 *
 * @return {Element} Element to render.
 */
const Edit = ( { attributes, setAttributes } ) => {
	const { amount } = attributes;

	const blockProps = useBlockProps();

	const hasAmount = parseInt( amount ) > 0;

	return (
		<>
			<InspectorControls>
				<PanelBody>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						type="number"
						step="any"
						label={ labelAmount }
						value={ amount ?? '' }
						onChange={ ( value ) =>
							setAttributes( { amount: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ hasAmount ? (
					<ServerSideRender
						block={ metadata.name }
						attributes={ attributes }
					/>
				) : (
					<Placeholder label={ title } icon="money">
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							type="number"
							step="any"
							label={ labelAmount }
							onChange={ () => {} }
							onBlur={ ( event ) =>
								setAttributes( { amount: event.target.value } )
							}
						/>

						<div style={ { width: '100%' } }>
							<Button variant="primary">{ labelAddForm }</Button>
						</div>
					</Placeholder>
				) }
			</div>
		</>
	);
};

registerBlockType( metadata.name, {
	title,
	icon: 'money',
	edit: Edit,
} );
