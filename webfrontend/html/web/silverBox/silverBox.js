/*
 * SilverBox 1.3.1
 * Flexible alert/modal with zero dependencies
 *
 * https://silverboxjs.ir/
 *
 * Released on: October 08, 2023
 */

// disables scroll
function silverBoxDisableScroll(select) {
    // selector 
    let silverBoxWrapper = document.querySelectorAll(select);
    // if the class node list is empty this code will be executed
    if (silverBoxWrapper.length <= 0) {
        document.body.classList.remove("stop-scrolling");
    }
     // if the class node list is not empty this code will be executed
    else {
        document.body.classList.add("stop-scrolling");
    }
}

// import
/** selects the silverBox container and closes the element*/
function silverBoxClose({ silverBoxElement, timer, onClose, element }) {
	// If timer config exists, silverBoxCloseAfterTimeout would get a uniqueID and will close the silverBox using that ID
	if (timer) {
		silverBoxCloseAfterTimeout(silverBoxElement);
	}

	// if there is a element passed to silverBoxClose object, the closest silverBox-container to that element would be removed
	else if (element) {
		element.closest(".silverBox-container").remove();
	}

	// Runs onClose function if it exits
	if (typeof onClose === "function") onClose();
}
// this function will remove a specific element with the unique ID and after a specific timeout
function silverBoxCloseAfterTimeout(silverBoxElement) {
	if (silverBoxElement) silverBoxElement.remove();

	silverBoxDisableScroll(".silverBox-overlay");
}

/**
 * Creates loading animation element
 * @returns {HTMLElement} - loading animation element
 */
function silverBoxLoadingAnimation() {
	// create loading element
	const loadingEl = document.createElement("span");

	// add className to loading element
	loadingEl.classList.add("silverBox-button-loading-animation");

	// return loading element
	return loadingEl;
}

/**
 * Creates predefined buttons
 * @param {Object} buttonName - Button config
 * @param {String} uniqClass - Button classList
 * @returns {HTMLElement} - Button
 */
function silverBoxButtonComponent(
	buttonName,
	uniqClass,
	defaultText,
	onCloseConfig
) {
	// create button element
	const buttonEl = document.createElement("button");

	// Check if the onClick property of buttonName exists and Add "click" event listener to buttonEl
	if (!!buttonName.onClick) buttonEl.addEventListener("click", buttonName.onClick);

	// loop over dataAttribute object entries
	Object.entries(buttonName.dataAttribute || {}).map(([key, value]) => {
		buttonEl.setAttribute(`data-${key}`, value);
	});

	// inline styles
	if (buttonName.bgColor) buttonEl.style.backgroundColor = buttonName.bgColor;
	if (buttonName.borderColor) buttonEl.style.borderColor = buttonName.borderColor;
	if (buttonName.textColor) buttonEl.style.color = buttonName.textColor;
	if (buttonName.disabled) buttonEl.disabled = buttonName.disabled;

	// add default className to button element
	buttonEl.classList.add("silverBox-button", uniqClass);

	// add given id to button element if it exits
	if (buttonName.id) buttonEl.id = buttonName.id;

	// add given className to button element if it exits
	if (buttonName.className) buttonEl.classList += ` ${buttonName.className}`;

	// if closeOnClick in config is true the code will be executed
	if (buttonName.closeOnClick !== false) {
		// Closes silverBox on click an run onClose function if it exits
		buttonEl.addEventListener("click", () => {
			silverBoxClose({ onClose: onCloseConfig, element: buttonEl });
		});
	}

	// if closeOnClick in config is false the code will be executed
	if (buttonName.loadingAnimation !== false) {
		// loading animation
		buttonEl.addEventListener("click", () => {
			buttonEl.classList.add("silverBox-loading-button");
		});
	}

	// create button text element
	const buttonTextSpan = document.createElement("span");

	// add "silverBox-button-text" className to buttonText span
	buttonTextSpan.classList = "silverBox-button-text";

	// add given/default text for buttonTextSpan element
	buttonTextSpan.textContent = buttonName.text ? buttonName.text : defaultText;

	// append iconStart / buttonTextSpan / silver box loadingAnimation / iconEnd
	buttonEl.append(
		createSilverBoxButtonIcon(buttonName.iconStart || ""),
		buttonTextSpan,
		silverBoxLoadingAnimation(),
		createSilverBoxButtonIcon(buttonName.iconEnd || "")
	);

	return buttonEl;
}

/**
 * create button Icon element
 * @param {String} iconSrc - Given image src
 * @returns {HTMLElement}
 */
function createSilverBoxButtonIcon(iconSrc) {
	// return an empty string if there is no iconSrc
	if (!iconSrc) return "";

	// create button Icon
	const buttonIcon = document.createElement("img");

	// add image to button Icon
	buttonIcon.src = iconSrc;

	// add default className to button Icon
	buttonIcon.classList = "silverBox-button-icon";

	return buttonIcon;
}

/**
 * Returns inputWrapper element based on given arguments from config
 * @param {String} type - type of input
 * @param {String} placeHolder - placeHolder of input
 * @param {Boolean} readOnly - value of input readonly attribute which is either true or false
 * @param {String} label - label name of input
 * @param {String} hint - hint of input
 * @param {String} width - width of input
 * @param {String} height - height of input
 * @param {Number} maxLength - maxLength attribute of input
 * @param {String} textAlign - specifies the position of texts in input
 * @param {String} fontSize - text fontSize of input
 * @param {String} placeHolderFontSize - placeHolder fontSize of input
 * @returns {HTMLElement} - inputWrapper element
 */
function silverBoxInputComponent({
	type,
	select,
	numberOnly,
	placeHolder,
	readOnly,
	label,
	hint,
	width,
	height,
	maxLength,
	textAlign,
	fontSize,
	placeHolderFontSize,
	name,
	className,
	id,
	value,
}) {
	// Create a wrapper div element for the input
	const inputWrapper = document.createElement("div");
	inputWrapper.classList = "silverBox-input-wrapper";

	// Create a label element and set its text content to the provided label
	const labelEl = document.createElement("label");
	labelEl.textContent = label;

	if (select) {
		// Create a select element if the 'select' flag is true
		const selectEl = document.createElement("select");
		selectEl.classList = "silverBox-select";

		// Iterate over the 'select' options array
		select.map((option) => {
			const optionEl = document.createElement("option");
			optionEl.value = option.value ?? "";
			optionEl.textContent = option.text ?? option.value ?? "";

			// Set the 'disabled' attribute if the option is disabled
			if (option.disabled) optionEl.setAttribute("disabled", "");

			// Set the 'selected' attribute if the option is selected
			if (option.selected) optionEl.setAttribute("selected", "");

			// Append the option element to the select element
			selectEl.append(optionEl);
		});

		// Append the select element to the input wrapper
		inputWrapper.append(selectEl);
	} else {
		// Create an input element (either input or textarea) based on the 'type'
		const isTextArea = type.toLowerCase() === "textarea";
		const inputEl = document.createElement(isTextArea ? "textarea" : "input");

		// Set the 'type' attribute for input elements (except for textarea)
		if (!isTextArea && type) inputEl.setAttribute("type", type);

		// Set the value of the input element to the provided value (or an empty string)
		inputEl.value = value ?? "";

		// Set the placeholder attribute if a placeholder value is provided
		if (placeHolder) inputEl.placeholder = placeHolder;

		// Set the maxLength attribute if a maxLength value is provided
		if (maxLength) inputEl.maxLength = maxLength;

		// Set the text alignment style if textAlign is provided
		if (textAlign) inputEl.style.textAlign = textAlign;

		// Set the width style if width is provided
		if (width) inputEl.style.width = width;

		// Set the height style if height is provided
		if (height) inputEl.style.height = height;

		// Set the font size style if fontSize is provided
		if (fontSize) inputEl.style.fontSize = fontSize;

		// Add an event listener to handle numberOnly behavior if numberOnly flag is true
		if (numberOnly) {
			inputEl.addEventListener("input", () => {
				inputEl.value = inputEl.value
					.replace(/[۰-۹]/g, (digit) => "۰۱۲۳۴۵۶۷۸۹.".indexOf(digit))
					.replace(/[^0-9.]/g, "");
			});
		}

		// Set the placeholder font size style if provided or fallback to fontSize
		const givenPHFS = placeHolderFontSize ?? fontSize ?? false;
		if (givenPHFS !== false)
			inputEl.style.setProperty("--silverBox-placeHolder-fontSize", givenPHFS);

		// Set the name attribute if a name value is provided
		if (name) inputEl.name = name;

		// Add the provided className to the input element's class list
		if (className) inputEl.classList += ` ${className}`;

		// Set the id attribute if an id value is provided
		if (id) inputEl.id = id;

		// Set the wrapper width to 'fit-content' if width is provided
		if (width) inputWrapper.style.width = "fit-content";

		// Set the 'readonly' attribute if readOnly flag is true
		if (readOnly) inputEl.setAttribute("readonly", "");

		// Append the label element to the input wrapper
		if (label) inputWrapper.append(labelEl);

		// Append the input element to the input wrapper
		inputWrapper.append(inputEl);
	}

	// Create a span element for the hint text and set its content to the provided hint
	const hintEl = document.createElement("span");
	hintEl.classList = "silverBox-input-hint";
	hintEl.textContent = hint ?? "";

	// Append the hint element to the input wrapper
	if (hint) inputWrapper.append(hintEl);

	// Return the input wrapper element
	return inputWrapper;
}

/**
 * append the component element to a parent element
 * @param {HTMLObjectElement} element - parent HTML element
 * @param {object} components - component items including (header,input and etc)
 */
function appendingToModal(element, components) {
	// loops through the component key
	Object.keys(components).map((item) => {
		// appends the components if they exist
		if (components[item]) element.append(components[item]);
	});
}

/**
 * Returns silverBox based on given argument from config
 * @param {string} direction - html direction value
 * @param {object} components - array of elements
 * @param {string} positionClassName - overlay of silverBox className
 * @param {boolean} isInput - boolean value
 * @param {string} theme - html data-theme attribute value which is either light or dark
 * @param {boolean} centerContent - specifies wether the content is centered or not
 * @returns {HTMLObjectElement} - silverBox overlay
 */
function createSilverBox({
	direction,
	components,
	positionClassName,
	theme = "light",
	centerContent,
}) {
	// main overlay
	const overlay = document.createElement("div");

	// add classlist to silverBox overlay
	overlay.classList.add("silverBox-container", positionClassName);

	// set a data for overlay
	overlay.dataset.theme = theme;

	// the modalBox
	const silverBoxModal = document.createElement("div");

	// add classlist to silverBox
	silverBoxModal.classList.add("silverBox");

	// set a direction for the modal
	if (direction) silverBoxModal.setAttribute("dir", direction);

	// centers the modal contents if the config is given
	if (centerContent) silverBoxModal.style.textAlign = "center";

	// append the components items (header,body,footer) to the silverBox
	appendingToModal(silverBoxModal, components);

	// if silverBox is not empty, it will be added to it's overlay
	if (silverBoxModal.childElementCount !== 0) overlay.append(silverBoxModal);

	// returns the silverBox overlay if it's not empty
	if (overlay.childElementCount !== 0) return overlay;
}

/**
 * Returns an icon based on the alert icon type and custom icon URL. If a custom icon URL is provided,
 * the function will create a user icon using the provided URL. Otherwise, it retrieves the requested icon
 * from the icons object and optionally centers it if the isCentred parameter is true.
 *
 * @param {String} alertIcon - The name of the alert icon to retrieve from the icons object (e.g. "warning").
 * @param {String} customIcon - The URL of a custom icon, if one is specified.
 * @param {String} customSvgIcon - The URL of a custom svg icon, if one is specified.
 * @param {Boolean} isCentred - Determines whether to center the icon or not (default is false).
 *
 * @returns {HTMLElement|null} - The requested icon element or null if no matching icon was found.
 */
const silverBoxIconsComponent = ({
	alertIcon,
	customIcon,
	customSvgIcon,
	isCentred = false,
	customIconClassName,
	customIconId,
	customSvgIconClassName,
	customSvgIconId,
}) => {
	// Check if a custom icon URL was provided.
	if (customIcon) {
		// Return a new custom icon element using the provided URL and clone it to avoid modifying the original icon.
		return silverBoxCreateCustomIcon(customIcon, isCentred, customIconClassName, customIconId, false).cloneNode(
			true
		);
	}

	// Check if a custom svg icon URL was provided.
	if (customSvgIcon) {
		// Return a new svg icon element using the provided URL and clone it to avoid modifying the original icon.
		return silverBoxCreateCustomIcon(
			customSvgIcon,
			isCentred,
			customSvgIconClassName,
			customSvgIconId,
			true
		).cloneNode(true);
	}

	// Check if the requested icon exists in the icons object.
	if (icons[alertIcon]) {
		// closeButton is not a node, so return it as is.
		if (alertIcon === "closeButton") return icons[alertIcon];

		// Retrieve the requested icon from the icons object and clone it to avoid modifying the original icon.
		const clonedIcon = icons[alertIcon].cloneNode(true);

		// Add the "silverBox-centered-icon" class to the cloned icon element.
		if (isCentred) clonedIcon.classList.add("silverBox-centered-icon");

		// Return the cloned icon element.
		return clonedIcon;
	}

	// Return null if no matching icon was found.
	return null;
};

// Create an object to store the available icons.
const icons = {
	warning: createIcon("silverBox-warning", "!"),
	success: createIcon("silverBox-tick-mark", "", "inside"),
	info: createIcon("silverBox-info", "i"),
	error: createIcon("silverBox-error", "", "x"),
	question: createIcon("silverBox-question", "?"),
	// X button
	closeButton:
		'<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 512 512"><line x1="368" y1="368" x2="144" y2="144" style="fill:none;stroke:#667085;stroke-linecap:round;stroke-linejoin:round;stroke-width:33px"/><line x1="368" y1="144" x2="144" y2="368" style="fill:none;stroke:#667085;stroke-linecap:round;stroke-linejoin:round;stroke-width:33px"/></svg>',
};

/**
 * Creates an icon element with the specified class name and child element (if any).
 * @param {String} className - The class name for the icon element.
 * @param {String} text - The text to display in the icon element (if any).
 * @param {String} childClass - The class name for a child element (if any).
 * @returns {HTMLElement} - The icon element.
 */
function createIcon(className, text, childClass) {
	// Create a new div element with the specified class name and class.
	const icon = document.createElement("div");

	// add given className to icon
	icon.classList = className;

	// add default classNames
	icon.classList.add("silverBox-icon", "silverBox-default-icon");

	// If childClass is defined, create a child div element with the specified class name and append it to the icon element.
	if (childClass) {
		// create child element
		const child = document.createElement("div");

		// add given child className
		child.classList = childClass;

		// appends the child element to icon
		icon.appendChild(child);
	}

	// If text is defined create a new span element with the text and append it to the icon element.
	else if (text) {
		const span = document.createElement("span");
		span.textContent = text;
		icon.appendChild(span);
	}

	// append icon into
	return icon;
}

/**
 * A function that creates a user icon element with the specified url.
 *
 * @param {String} customIcon - The URL for the user icon.
 * @param {Boolean} isCentred - Whether to center the icon or not.
 * @param {String} customIconClassName - A custom class to add to the icon element.
 * @param {String} customIconId - A custom ID to add to the icon element.
 * @returns {HTMLElement} - The user icon element created.
 */
function silverBoxCreateCustomIcon(customIcon, isCentred, className, id, isSvg) {
	// create a wrapper for customIcon
	const customIconWrapper = document.createElement("div");

	// add className to customIcon wrapper
	customIconWrapper.classList.add(`silverBox-image-wrapper`);

	// give wrapper a centred class if it's given
	if (isCentred) customIconWrapper.classList.add("silverBox-centered-icon");

	// Adds customIcon Id
	if (id) customIconWrapper.id = id;

	// Adds customIcon class
	if (className) customIconWrapper.classList += ` ${className}`;

	// if there is a svg config the svg code will be added to the wrapper
	if (!!isSvg) {
		customIconWrapper.innerHTML += customIcon;
	}

	// if there is no svg config the image element will be created
	else {
		const img = document.createElement("img");
		img.src = customIcon;
		img.classList = "silverBox-icon silverBox-custom-icon";
		customIconWrapper.append(img);
	}

	return customIconWrapper;
}

/** imports */

/**
 * Returns headerWrapper based on given arguments from config
 * @param {Object} titleConfig - silverBox title Config
 * @param {String} icon - silverBox icon
 * @param {Boolean} showCloseButton - silverBox closeButton
 * @param {Boolean} centerContent - center silverBox header content
 * @returns {Object} - headerWrapper element
 */
function silverBoxHeaderComponent({
	titleConfig,
	icon,
	showCloseButton,
	centerContent,
	onCloseConfig,
}) {
	// header wrapper
	const headerWrapper = document.createElement("div");

	// add default className to headerWrapper
	headerWrapper.classList.add("silverBox-header-wrapper");

	// icon and closeButton wrapper
	const iconWrapper = document.createElement("div");

	// add default className to iconWrapper
	iconWrapper.classList.add("silverBox-icon-wrapper");

	// title wrapper
	const title = document.createElement("h2");

	// add default className to title
	title.classList.add("silverBox-header-title");

	/**
	 * titleConfig should be an object. So if only the 'text' has been provided,
	 * it needs to be converted to an object with a 'text' property.
	 */
	if (typeof titleConfig === "string") titleConfig = { text: titleConfig };

	// check if customIcon is needed
	if (titleConfig?.customIcon) {
		// stores returned customIcon element into a variable
		const customIcon = silverBoxIconsComponent({
			customIcon: titleConfig.customIcon,
		});

		// if titleCustomIcon id exists, the img element of the customIcon will receive given Id
		if (titleConfig?.customIconId)
			customIcon.children[0].id = titleConfig.customIconId;

		// if titleCustomIcon className exists, the img element of the customIcon will receive given class
		if (titleConfig?.customIconClassName) {
			customIcon.children[0].classList += ` ${titleConfig.customIconClassName}`;
		}

		// append the customIcon into the title
		title.append(customIcon);
	}
	// check if customSvgIcon is needed
	else if (titleConfig?.customSvgIcon) {
		// stores returned customSvgIcon element into a variable
		const customSvgIcon = silverBoxIconsComponent({
			customSvgIcon: titleConfig.customSvgIcon,
		});

		// if titleSvgCustomIcon id exists, the img element of the customIcon Wrapper will receive given Id
		if (titleConfig?.customSvgIconId)
			customSvgIcon.children[0].id = titleConfig.customSvgIconId;

		// if titleSvgCustomIcon class exists, the img element of the customIcon Wrapper will receive given class
		if (titleConfig?.customSvgIconClassName) {
			customSvgIcon.children[0].classList += ` ${titleConfig.customSvgIconClassName}`;
		}
		// append the customSvgIcon into the title
		title.append(customSvgIcon);
	}
	// check if alertIcon is needed
	else if (titleConfig?.alertIcon) {
		// stores returned alertIcon element into a variable
		const alertIcon = silverBoxIconsComponent({
			alertIcon: titleConfig.alertIcon,
		});

		// append the alertIcon into the title
		title.append(alertIcon);
	}
	// checks if parentELement has a icon, if true the has-icon class will be given
	if (title.childElementCount >= 1)
		title.classList.add("silverBox-title-has-icon");

	// if centerContent is true the title children will be centred
	if (centerContent) title.classList.add("silverBox-title-centred");

	// check if textConfig exists
	if (titleConfig?.text) {
		// create titleSpan element
		const titleSpan = document.createElement("span");

		// add a default className to the title element with some related styles
		title.classList.add("silverBox-title-text");

		// add the given text to titleSpan element
		titleSpan.textContent = titleConfig.text;

		// append the titleSpan to the title element
		title.append(titleSpan);
	}

	// create a span element for x button
	const closeButtonEl = document.createElement("span");

	// add "x" icon as a SVG to the closeButtonEl
	closeButtonEl.innerHTML = silverBoxIconsComponent({ alertIcon: "closeButton" });

	// add a default className to "x" button
	closeButtonEl.classList.add("silverBox-close-button");

	// add a onclick event for the closeButtonEl to close the Modal
	// closeButtonEl.onclick = silverBoxCloseButtonOnClick({ hasOverlay: true });
	closeButtonEl.addEventListener("click", () => {
		silverBoxClose({
			onClose: onCloseConfig,
			element: closeButtonEl,
		});
	});

	// add icon to iconWrapper
	if (icon) iconWrapper.appendChild(icon);

	// add closeButton to iconWrapper
	if (showCloseButton) iconWrapper.appendChild(closeButtonEl);

	// appends the icon Wrapper to headerWrapper
	if (iconWrapper.childElementCount >= 1) headerWrapper.append(iconWrapper);

	// add title to headerWrapper
	if (titleConfig) headerWrapper.appendChild(title);

	// return headerWrapper
	return headerWrapper;
}

/**
 * Creates bodyWrapper and appends html config, text config, button component, input component to it.
 * @param {String} htmlContent - The HTML structure to be displayed.
 * @param {String} bodyText - The text content to be displayed.
 * @param {String} components - The array of components to be appended.
 * @returns {HTMLElement} - The created body wrapper element.
 */
function silverBoxBodyComponent({ htmlContent, bodyText, components, isInput }) {
	// create bodyWrapper for html,text,inputComponent,buttonComponent
	const bodyWrapper = document.createElement("div");

	// add default className to silverBox-body
	bodyWrapper.classList = "silverBox-body-wrapper";

	if (htmlContent) {
		// create htmlStructure element
		const htmlStructure = document.createElement("div");

		// add a default className for the htmlStructure element
		htmlStructure.classList.add("silverBox-body-description");

		// add the given html structure to the htmlStructure element
		if (htmlContent.outerHTML) htmlStructure.append(htmlContent);
		else htmlStructure.innerHTML = htmlContent;

		// add the htmlStructure to it's wrapper
		bodyWrapper.appendChild(htmlStructure);
	} else if (bodyText) {
		// create textStructure element
		const textStructure = document.createElement("p");

		// add the given textConfig to the textStructure element
		textStructure.textContent = bodyText;

		// add a default className to the textStructure element
		textStructure.classList.add("silverBox-body-description");

		// append the textStructure to it's wrapper
		bodyWrapper.appendChild(textStructure);
	}

	// Create form variable to contain a form element if it's needed
	let form;

	// checks if we have inputs in the given config, if true the elements will be added to a form elements, else there will be no form elements
	if (isInput) {
		// create  form element for inputs
		form = document.createElement("form");

		// add classlist to form element
		form.classList.add("silverBox-form");

		// submit event listener for silverBox form
		form.addEventListener("submit", (e) => {
			// form preventDefault
			e.preventDefault();
		});

		// appends the form into the bodyWrapper
		bodyWrapper.append(form);
	}
	// append all components to modal by calling the "appendingToModal" helper function
	appendingToModal(form ? form : bodyWrapper, components);

	return bodyWrapper;
}

/**
 * Returns footer element based on arguments as text
 * @param {string} footerContent - footer HTML content
 * @returns {Element} - footer element
 */
function silverBoxFooterComponent({ footerContent }) {
	// creates footer
	const footerEl = document.createElement("div");

	// add className to footer element
	footerEl.classList.add("silverBox-footer-wrapper");

	// creates hr line
	const line = document.createElement("hr");

	// appends line to footerEl
	footerEl.append(line);

	// appends footer to footerEl innerHTML
	footerEl.innerHTML += footerContent;

	// returns the footer
	return footerEl;
}

/**
 * removes all silverBox's
 * @param {string} - value of silverBox that wants to be removed
 */
function removeAllSilverBoxes(index) {
    // converts the index to lowercase
    index = index.toLowerCase();
    // selector
    const silverBoxes = document.querySelectorAll('.silverBox-container');

    // changes the indexes
    if (index === "first") index = 1;
    if (index === "last") index = silverBoxes.length;

    // all
    if (index === "all") {
        for (let i = 0; i < silverBoxes.length; i++) {
            silverBoxes[i].remove();
        }
    }
    // number
    else if (Number(index) > 0) {
        silverBoxes[Number(index) - 1].remove();
    }
}

function silverBoxRemoveLoadings(animationIndex) {
    // select SilverBox buttonWrapper
    let silverBoxButtonWrapper = document.querySelectorAll('.silverBox-button-wrapper');

    // converts the given value to lowerCase
    animationIndex.toLowerCase();

    // covert the indexes 
    if (animationIndex === 'first') animationIndex = 1;
    if (animationIndex === 'last') animationIndex = silverBoxButtonWrapper.length;

    // removes all modal's button's loading
    if (animationIndex === 'all') {
        for (let i = 0; i < silverBoxButtonWrapper.length; i++) {
            silverBoxButtonWrapper[i].childNodes.forEach(button => {
                button.classList.remove('silverBox-loading-button');
            });

        }

    }
    // removes the nth modal's button's loading
    else if (Number(animationIndex) > 0) {
        silverBoxButtonWrapper[Number(animationIndex) - 1].childNodes.forEach(button => {
            button.classList.remove('silverBox-loading-button');
        });
    }

}

// Function to convert number values to strings with 's' suffix for seconds
const validateDuration = (value) => {
	// Check if the value is a valid number
	if (Number(value)) {
		// If it is a number, add 'ms' suffix and return the value as a string
		return `${value}ms`;
	}

	// Check if the value is a valid number with "ms" or "s" suffix, or return "300ms" as the default
	return (parseInt(value) || parseFloat(value)) &&
		(value.endsWith("ms") || value.endsWith("s"))
		? value
		: "300ms";
};

// imports

const silverBoxTimerBar = ({ silverBoxElement, timerConfig, onClose }) => {
	// gives the pauseOnHover and showBar config in timer a default value if they're not given
	if (!("showBar" in timerConfig)) timerConfig.showBar = true;
	if (!("pauseOnHover" in timerConfig)) timerConfig.pauseOnHover = true;

	// select silverBox to append the timerBar element
	let silverBox = document.querySelectorAll(".silverBox");
	silverBox = silverBox[silverBox.length - 1];

	// create a timerBar element with it's wrapper to track the remaining time before closing the silverBox
	const timerBar = document.createElement("div");
	timerBar.classList = "timer-bar";

	const timerBarWrapper = document.createElement("div");
	timerBarWrapper.classList = "timer-bar-wrapper";

	// appends the timerBar inside a wrapper

	if (timerConfig.duration) timerBarWrapper.append(timerBar);

	// defining the animation duration based on the given timer
	timerBar.style.animation = `timer ${validateDuration(
		timerConfig.duration
	)} linear`;

	// checks if the pauseTimerOnHover config is not false (it could either be )
	if (timerConfig?.pauseOnHover !== false && silverBox) {
		silverBox.addEventListener("mouseover", () => {
			timerBar.style.animationPlayState = "paused";
		});
		silverBox.addEventListener("mouseout", () => {
			timerBar.style.animationPlayState = "running";
		});
	}

	// appending the timerBar to silverBox, if users wants it
	if (silverBox && timerConfig?.showBar) {
		silverBox.append(timerBarWrapper);

		// removes the specific element after the given timeout
		timerBar.addEventListener("animationend", () => {
			silverBoxClose({
				silverBoxElement,
				timer: timerConfig.duration,
				onClose,
			});
		});
	} else {
		setTimeout(() => {
			silverBoxClose({
				silverBoxElement,
				timer: timerConfig.duration,
				onClose,
			});
		}, timerConfig.duration);
	}
};

/**
 * Applies animation using the provided configuration.
 * @param {Object} config - The animation configuration.
 * @returns {String} - The final animation configuration.
 */
const applyAnimation = (config) => {
	// default values for animation properties
	const defaultValues = {
		name: "popUp",
		duration: "300ms",
		timingFunction: "linear",
		delay: "0ms",
		iterationCount: "1",
		direction: "normal",
		fillMode: "none",
	};

	// Normalize duration and delay values
	const normalizedConfig = {
		...defaultValues,
		...config,
		duration: validateDuration(config.duration) || defaultValues.duration,
		delay: validateDuration(config.delay) || defaultValues.delay,
	};

	// Destructure animation config keys
	const {
		name,
		duration,
		timingFunction,
		delay,
		iterationCount,
		direction,
		fillMode,
	} = normalizedConfig;

	return `${name} ${duration} ${timingFunction} ${delay} ${iterationCount} ${direction} ${fillMode}`;
};

// import components

/**
 * SilverBox function that creates silverBox by provided config.
 * @param {Object} config - object of related keys to silverBox settings.
 */
function silverBox(config = {}) {
	try {
		// Logs out an error if silverBox config is empty.
		if (Object.keys(config).length === 0) {
			throw new Error("You can't create silverBox with an empty config.");
		}

		// Checks if preOpen config exists, then executes the callback which has been provided by user
		config.preOpen?.();

		// Calls the "removeAllSilverBoxes" function to remove silverBox by provided config.
		if ("removeSilverBox" in config) {
			removeAllSilverBoxes(config.removeSilverBox);
		}

		// Calls the "silverBoxRemoveLoadings" function to remove silverBox button loadings by provided config.
		if ("removeLoading" in config) {
			silverBoxRemoveLoadings(config.removeLoading);
		}

		// Object of all silverBox components.
		const components = {};

		// Object of body wrapper related components.
		const bodyComponents = {};

		// Create input wrapper for all inputs in provided config.
		const inputWrapper = document.createElement("div");

		// Add default className for "inputWrapper".
		inputWrapper.classList = "silverBox-input-container";

		// Create button wrapper for all buttons provided in config.
		const buttonWrapper = document.createElement("div");

		// Add default className for "buttonWrapper".
		buttonWrapper.classList = "silverBox-button-wrapper";

		// Create a function that returns icon related properties provided in config.
		const iconsConfig = () => {
			return {
				alertIcon: config.alertIcon,
				customIcon: config.customIcon,
				isCentred: config.centerContent,
				customIconClassName: config.customIconClassName,
				customIconId: config.customIconId,
				customSvgIcon: config.customSvgIcon,
				customSvgIconClassName: config.customSvgIconClassName,
				customSvgIconId: config.customSvgIconId,
			};
		};

		// Assign "silverBoxHeaderComponent" to a constant to put it inside "components" object.
		const headerLayout = silverBoxHeaderComponent({
			titleConfig: config.title,
			icon: silverBoxIconsComponent(iconsConfig()),
			showCloseButton: config.showCloseButton,
			centerContent: config.centerContent,
			onCloseConfig: config.onClose,
		});

		// Assign "headerLayout" constant as header key in "components" object.
		if (headerLayout.childElementCount !== 0) components.header = headerLayout;

		if ("input" in config) {
			/**
			 * Returns an object with specified configuration properties for an input element.
			 * @param {Object} selector - The selector object containing input configuration properties.
			 * @returns {Object} - The input configuration object.
			 */
			const inputConfig = (selector) => {
				return {
					type: "type" in selector ? selector.type : "",
					select: selector.select,
					numberOnly: selector.numberOnly,
					hint: selector.hint,
					label: selector.label,
					placeHolder: selector.placeHolder,
					readOnly: selector.readOnly,
					width: selector.width,
					height: selector.height,
					maxLength: selector.maxLength,
					textAlign: selector.textAlign,
					fontSize: selector.fontSize,
					placeHolderFontSize: selector.placeHolderFontSize,
					name: selector.name,
					className: selector.className,
					id: selector.id,
					value: selector.value,
				};
			};

			const multiplyByCheck = (selector) => {
				// If there is no "multiplyBy" in config, this code is executed.
				if (!("multiplyBy" in selector)) selector.multiplyBy = 1;

				// Loops throw "multiplyBy" config to creates the given number of inputs by checking "multiplyBy" property.
				for (let i = 1; i <= selector.multiplyBy; i++) {
					inputWrapper.append(
						silverBoxInputComponent(inputConfig(selector))
					);
				}
			};

			// Loops throw "config.input" if it's an array and adds it to the input wrapper by calling
			// "multiplyByCheck". If there it's not an array it will be called only once.
			Array.isArray(config.input)
				? config.input.forEach((input) => multiplyByCheck(input))
				: multiplyByCheck(config.input);

			// Add "inputWrapper" to "component" object.
			if (inputWrapper.childElementCount) bodyComponents.input = inputWrapper;
		}

		// Array of Buttons config.
		const buttonsConfig = [
			{
				type: "confirmButton",
				text: "Confirm",
			},
			{
				type: "denyButton",
				text: "Deny",
			},
			{
				type: "cancelButton",
				text: "Cancel",
			},
			{
				type: "customButton",
				text: "Custom",
			},
		];

		// Loop over buttons config in order to create them.
		for (const button of buttonsConfig) {
			if (button.type in config && config[button.type].showButton !== false) {
				buttonWrapper.append(
					silverBoxButtonComponent(
						config[button.type],
						`silverBox-${button.text.toLowerCase()}-button`,
						button.text,
						config.onClose
					)
				);
			}
		}

		// Sets "buttonWrapper" direction.
		if ("buttonsDirection" in config) {
			buttonWrapper.style.direction = config.buttonsDirection;
		}

		// Pushes the "buttonWrapper" inside the "bodyComponents" for appending it to silverBox.
		if (buttonWrapper.childElementCount) bodyComponents.button = buttonWrapper;

		// Create "bodyComponent" variable config for "silverBoxBodyComponent".
		const bodyLayoutConfig = silverBoxBodyComponent({
			htmlContent: config.html,
			bodyText: config.text,
			components: bodyComponents,
			isInput: config.input,
		});

		// Adds "bodyComponentConfig" to "components" object to append it inside silverBox if it's not empty.
		if (bodyLayoutConfig.childElementCount) components.body = bodyLayoutConfig;

		// Adds "footer" config as "footer" key in "components" Object.
		if (config.footer) {
			components.footer = silverBoxFooterComponent({
				footerContent: config.footer,
			});
		}

		/**
		 * This function adds a sample modal configuration to the document body.
		 * @param {String} className - The class name used for positioning the modal box.
		 * @param {Boolean} isInputValue - Determines if the modal box contains an input field.
		 * @returns {void}
		 */
		const modalSampleConfig = (className) => {
			if (Object.keys(components).length === 0) return null;

			const createdSilverBox = createSilverBox({
				components: components,
				positionClassName: className,
				theme: config.theme,
				direction: config.direction,
				centerContent: config.centerContent,
			});

			document.body.append(createdSilverBox);

			return createdSilverBox;
		};

		// If "position" exists in "config",sets the "position" variable to "silverBox-${config.position}"
		// otherwise, it sets it to the value `"silverBox-overlay"`.
		const position =
			"position" in config
				? `silverBox-${config.position}`
				: "silverBox-overlay";

		// Calls "modalSampleConfig" with value provided from "position" to create silverBox.
		// Store it to be used in the returned methods at the end.
		const silverBoxElement = modalSampleConfig(position);

		// If "timer" is provided in config, the modal will be removed after the given time.
		if ("timer" in config) {
			// changes the title config to an object if the given value is a number, so as a result we can use this config as either an object or a number.
			if (
				typeof config.timer === "number" ||
				typeof config.timer === "string"
			) {
				config.timer = { duration: config.timer };
			}

			// Handle the timerBar functionalities
			silverBoxTimerBar({
				silverBoxElement,
				timerConfig: config.timer,
				pauseTimerOnHover: config.pauseTimerOnHover,
				showTimerBar: config.showTimerBar,
				onClose: config.onClose,
			});
		}

		// Select silverBox overlay to give it an eventListener.
		let silverBoxOverlay = document.querySelectorAll(".silverBox-overlay");
		silverBoxOverlay = silverBoxOverlay[silverBoxOverlay.length - 1];

		// if the clicked element has classList of silverBox-overlay this code will be executed.
		if (silverBoxOverlay && config.closeOnOverlayClick !== false) {
			silverBoxOverlay.addEventListener("click", (e) => {
				// closes the modal if the user clicks on the overlay (outside of the modal).
				if (e.target === silverBoxOverlay) {
					silverBoxClose({
						onClose: config.onClose,
						element: silverBoxOverlay,
					});
				}
				// checks for silverBox after removing wrapper.
				silverBoxDisableScroll(".silverBox-overlay");
			});
		}

		// Checks for silverBox after it's created.
		silverBoxDisableScroll(".silverBox-overlay");

		// If silverBoxId is in config
		if ("silverBoxId" in config) silverBoxElement.id = config.silverBoxId;

		// Add silverBox className
		if ("silverBoxClassName" in config) {
			silverBoxElement.classList += ` ${config.silverBoxClassName}`;
		}

		// Add animation to silverBox
		if ("animation" in config) {
			// Select "silverBox" to give it animation
			const silverBox = silverBoxElement.querySelector(".silverBox");

			if (!!silverBox) {
				// Set animation for the silverBox element based on the configuration provided.
				// If "animation" is an array, each animation value will be added to silverBox as part of the animation sequence.
				// If "animation" is an object, it will be called once and its values will be applied as an animation to silverBox.
				silverBox.style.animation = Array.isArray(config.animation)
					? config.animation
							.map((animation) => applyAnimation(animation))
							.join(", ")
					: applyAnimation(config.animation);
			}
		}

		// Check if the "didOpen" property exists in the "config" object
		config.didOpen?.();

		if (silverBoxElement === null) return null;

		return {
			remove: () => {
				document.body.removeChild(silverBoxElement);
			},
			removeLoading: (selector = "") => {
				const buttons = silverBoxElement.querySelectorAll(
					`button${selector}`
				);
				buttons.forEach((button) => {
					button.classList.remove("silverBox-loading-button");
				});
			},
		};
	} catch (error) {
		throw error;
	}
}